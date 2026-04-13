/**
 * Corido Vendor Tracker — Admin JavaScript
 *
 * 1. Listivo listing search (add mode) — real-time, auto-fills visible form fields
 * 2. Vendor typeahead (add/edit)
 * 3. Live payout preview tied to the selling_price input
 * 4. WP Media uploader for item images
 * 5. Image removal via AJAX
 * 6. Confirm-before-delete
 */
/* global CVT, wp */
(function ($) {
	'use strict';

	// -------------------------------------------------------------------------
	// Utility
	// -------------------------------------------------------------------------
	function escHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	// -------------------------------------------------------------------------
	// 1. Listivo Listing Search — add mode only
	//    Fires from the first character; fills title, category, selling_price.
	// -------------------------------------------------------------------------
	var $listingSearch = $('#cvt-listing-search');
	var $listingSugg   = $('#cvt-listing-suggestions');
	var listingTimer   = null;

	if ($listingSearch.length) {

		$listingSearch.on('input', function () {
			clearTimeout(listingTimer);
			var q = $(this).val().trim();

			if (q.length === 0) {
				$listingSugg.hide().empty();
				return;
			}

			// Show a subtle loading state after a short pause.
			listingTimer = setTimeout(function () {
				$.ajax({
					url:    CVT.ajax_url,
					method: 'GET',
					data:   { action: 'cvt_listing_search', nonce: CVT.nonce, q: q },
					success: function (res) {
						$listingSugg.empty();

						if (!res.success || !res.data.length) {
							$listingSugg.html(
								'<div class="cvt-suggestion-item cvt-suggestion-empty">No published listings found for "' + escHtml(q) + '"</div>'
							).removeAttr('hidden').show();
							return;
						}

						$.each(res.data, function (i, listing) {
							var thumb = listing.thumbnail
								? '<img src="' + escHtml(listing.thumbnail) + '" class="cvt-suggestion-thumb" alt="">'
								: '<span class="cvt-suggestion-thumb cvt-suggestion-thumb--placeholder"></span>';

							var price = listing.price
								? '<span class="cvt-suggestion-price">KES ' + escHtml(Number(listing.price).toLocaleString()) + '</span>'
								: '';

							var cat = listing.category
								? '<span class="cvt-suggestion-cat">· ' + escHtml(listing.category) + '</span>'
								: '';

							var $row = $(
								'<div class="cvt-suggestion-item cvt-listing-suggestion" role="option" tabindex="-1">' +
								thumb +
								'<div class="cvt-suggestion-body">' +
								'<strong class="cvt-suggestion-title">' + escHtml(listing.title) + '</strong>' +
								'<span class="cvt-suggestion-meta">' + price + cat + '</span>' +
								'</div></div>'
							);

							$row.on('click', function () { selectListing(listing); });
							$listingSugg.append($row);
						});

						$listingSugg.removeAttr('hidden').show();
					}
				});
			}, 200);   // 200 ms debounce — fast enough to feel real-time
		});

		// Keyboard navigation.
		$listingSearch.on('keydown', function (e) {
			var $items  = $listingSugg.find('.cvt-listing-suggestion');
			var $active = $items.filter('.is-active');

			if (e.key === 'ArrowDown') {
				e.preventDefault();
				var $next = $active.length ? $active.removeClass('is-active').next('.cvt-listing-suggestion') : $items.first();
				$next.addClass('is-active').focus();
			} else if (e.key === 'ArrowUp') {
				e.preventDefault();
				$active.removeClass('is-active').prev('.cvt-listing-suggestion').addClass('is-active');
			} else if (e.key === 'Enter') {
				if ($active.length) { e.preventDefault(); $active.trigger('click'); }
			} else if (e.key === 'Escape') {
				$listingSugg.hide().empty();
				$listingSearch.focus();
			}
		});

		// Click outside closes the dropdown.
		$(document).on('click', function (e) {
			if (!$(e.target).closest('#cvt-listing-search-wrap').length) {
				$listingSugg.hide();
			}
		});

		// "Change" button clears selection and reopens search.
		$(document).on('click', '#cvt-listing-change', function () {
			clearListing();
			$listingSearch.focus();
		});
	}

	function selectListing(listing) {
		// --- Populate VISIBLE form fields ---

		// Title
		$('#title').val(listing.title);

		// Selling price → triggers payout preview update below
		if (listing.price) {
			$('#selling_price').val(listing.price).trigger('input');
		}

		// Category: match by text (case-insensitive) against the <select> options
		if (listing.category) {
			var matched = false;
			$('#category option').each(function () {
				if ($(this).text().trim().toLowerCase() === listing.category.toLowerCase()) {
					$('#category').val($(this).val());
					matched = true;
					return false; // break
				}
			});
			// If not found in the list, append a temporary option so it isn't lost
			if (!matched && listing.category) {
				$('#category').append(
					$('<option>', { value: listing.category, text: listing.category, selected: true })
				);
			}
		}

		// --- Populate HIDDEN fields ---
		$('#cvt-field-description').val(listing.excerpt || '');
		$('#cvt-field-listing-url').val(listing.url);

		// --- Update listing chip ---
		var $thumb = $('#cvt-listing-thumb');
		if (listing.thumbnail) {
			$thumb.attr('src', listing.thumbnail).removeAttr('hidden').show();
		} else {
			$thumb.hide();
		}
		$('#cvt-listing-chip-title').text(listing.title);
		$('#cvt-listing-chip-url').attr('href', listing.url);

		// Switch UI: hide search input, show chip
		$listingSugg.hide().empty();
		$('#cvt-listing-search-state').hide();
		$('#cvt-listing-selected').removeAttr('hidden').show();
	}

	function clearListing() {
		$('#title').val('');
		$('#selling_price').val('').trigger('input');
		$('#category').val('');
		$('#cvt-field-description').val('');
		$('#cvt-field-listing-url').val('');

		$listingSearch.val('');
		$('#cvt-listing-selected').hide();
		$('#cvt-listing-search-state').show();
	}

	// -------------------------------------------------------------------------
	// 2. Vendor Typeahead
	// -------------------------------------------------------------------------
	var $vendorSearch  = $('#cvt-vendor-search');
	var $vendorId      = $('#vendor_id');
	var $vendorSugg    = $('#cvt-vendor-suggestions');
	var vendorTimer    = null;
	var vendorLabel    = $vendorSearch.val(); // preserve pre-selected label

	if ($vendorSearch.length) {
		$vendorSearch.wrap('<div id="cvt-vendor-search-wrap" style="position:relative;"></div>');

		$vendorSearch.on('input', function () {
			clearTimeout(vendorTimer);
			var q = $(this).val().trim();

			if (q !== vendorLabel) { $vendorId.val(''); }

			if (q.length === 0) { $vendorSugg.hide().empty(); return; }

			vendorTimer = setTimeout(function () {
				$.ajax({
					url:    CVT.ajax_url,
					method: 'GET',
					data:   { action: 'cvt_vendor_search', nonce: CVT.nonce, q: q },
					success: function (res) {
						$vendorSugg.empty();
						if (!res.success || !res.data.length) {
							$vendorSugg.append('<div class="cvt-suggestion-item cvt-suggestion-empty">No vendors found.</div>');
						} else {
							$.each(res.data, function (i, v) {
								var $row = $(
									'<div class="cvt-suggestion-item">' +
									'<strong>' + escHtml(v.name) + '</strong>' +
									'<span class="cvt-suggestion-phone">' + escHtml(v.phone_primary) + '</span>' +
									'</div>'
								);
								$row.on('click', function () {
									vendorLabel = v.name + ' (' + v.phone_primary + ')';
									$vendorSearch.val(vendorLabel);
									$vendorId.val(v.id);
									$vendorSugg.hide().empty();
								});
								$vendorSugg.append($row);
							});
						}
						$vendorSugg.removeAttr('hidden').show();
					}
				});
			}, 200);
		});

		$vendorSearch.on('keydown', function (e) {
			var $items  = $vendorSugg.find('.cvt-suggestion-item');
			var $active = $items.filter('.is-active');
			if (e.key === 'ArrowDown') {
				e.preventDefault();
				($active.length ? $active.removeClass('is-active').next() : $items.first()).addClass('is-active');
			} else if (e.key === 'ArrowUp') {
				e.preventDefault();
				$active.removeClass('is-active').prev().addClass('is-active');
			} else if (e.key === 'Enter' && $active.length) {
				e.preventDefault(); $active.trigger('click');
			} else if (e.key === 'Escape') {
				$vendorSugg.hide();
			}
		});

		$(document).on('click', function (e) {
			if (!$(e.target).closest('#cvt-vendor-search-wrap').length) {
				$vendorSugg.hide();
			}
		});
	}

	// -------------------------------------------------------------------------
	// 3. Live Payout Preview — tied to #selling_price (both modes)
	// -------------------------------------------------------------------------
	var $sellingPrice = $('#selling_price');

	if ($sellingPrice.length) {
		$sellingPrice.on('input change', function () {
			var price = parseFloat($(this).val()) || 0;
			if (price <= 0) {
				$('#preview-commission').text('KES 0.00');
				$('#preview-payout').text('KES 0.00');
				return;
			}
			$.ajax({
				url:    CVT.ajax_url,
				method: 'GET',
				data:   { action: 'cvt_payout_preview', nonce: CVT.nonce, price: price },
				success: function (res) {
					if (res.success) {
						$('#preview-commission').text(res.data.formatted.commission);
						$('#preview-payout').text(res.data.formatted.payout);
					}
				}
			});
		}).trigger('change');
	}

	// -------------------------------------------------------------------------
	// 4. WP Media Uploader — Add Images
	// -------------------------------------------------------------------------
	var mediaFrame;
	var newImageIds = [];

	$('#cvt-add-image').on('click', function (e) {
		e.preventDefault();
		if (mediaFrame) { mediaFrame.open(); return; }

		mediaFrame = wp.media({
			title:    CVT.i18n.select_image,
			button:   { text: CVT.i18n.use_image },
			multiple: true,
			library:  { type: 'image' }
		});

		mediaFrame.on('select', function () {
			$.each(mediaFrame.state().get('selection').toJSON(), function (i, att) {
				var thumb = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
				$('#cvt-image-grid').append(
					'<div class="cvt-image-thumb" data-new-id="' + att.id + '">' +
					'<img src="' + escHtml(thumb) + '" alt="">' +
					'<button type="button" class="cvt-image-remove" title="Remove">×</button>' +
					'</div>'
				);
				newImageIds.push(att.id);
				$('#cvt_image_ids').val(newImageIds.join(','));
			});
		});

		mediaFrame.open();
	});

	// -------------------------------------------------------------------------
	// 5. Image Removal
	// -------------------------------------------------------------------------
	$(document).on('click', '.cvt-image-remove', function () {
		var $thumb = $(this).closest('.cvt-image-thumb');
		var rowId  = $thumb.data('row-id');
		var newId  = $thumb.data('new-id');
		var itemId = $thumb.data('item-id');

		if (newId) {
			newImageIds = newImageIds.filter(function (id) { return id !== newId; });
			$('#cvt_image_ids').val(newImageIds.join(','));
			$thumb.remove();
			return;
		}
		if (!rowId || !itemId) { return; }

		$.ajax({
			url:    CVT.ajax_url,
			method: 'POST',
			data:   { action: 'cvt_remove_item_image', nonce: CVT.nonce, item_id: itemId, image_row_id: rowId },
			success: function (res) {
				if (res.success) { $thumb.remove(); }
				else { alert(res.data && res.data.message ? res.data.message : 'Could not remove image.'); }
			}
		});
	});

	// -------------------------------------------------------------------------
	// 6. Confirm Destructive Actions
	// -------------------------------------------------------------------------
	$(document).on('click', '.cvt-delete-link', function (e) {
		if (!confirm(CVT.i18n.confirm_delete)) { e.preventDefault(); }
	});

})(jQuery);
