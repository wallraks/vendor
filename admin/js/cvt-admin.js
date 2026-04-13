/**
 * Corido Vendor Tracker — Admin JavaScript
 *
 * Handles:
 *  1. Listivo listing search (add mode) — search, select, auto-fill hidden inputs
 *  2. Vendor typeahead (add/edit mode)
 *  3. Live payout preview on selling price change (edit mode)
 *  4. WP Media uploader for item images
 *  5. Image removal via AJAX
 *  6. Confirm-before-delete for destructive links
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

	function formatKes(num) {
		var n = parseFloat(num) || 0;
		return 'KES ' + n.toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}

	// -------------------------------------------------------------------------
	// 1. Listivo Listing Search (add mode only — #cvt-listing-search)
	// -------------------------------------------------------------------------
	var $listingSearch   = $('#cvt-listing-search');
	var $listingSugg     = $('#cvt-listing-suggestions');
	var listingTimer     = null;

	if ($listingSearch.length) {

		$listingSearch.on('input', function () {
			clearTimeout(listingTimer);
			var q = $(this).val().trim();

			if (q.length < 2) {
				$listingSugg.hide().empty();
				return;
			}

			listingTimer = setTimeout(function () {
				$.ajax({
					url:    CVT.ajax_url,
					method: 'GET',
					data:   { action: 'cvt_listing_search', nonce: CVT.nonce, q: q },
					success: function (res) {
						$listingSugg.empty();
						if (!res.success || !res.data.length) {
							$listingSugg.append(
								'<div class="cvt-suggestion-item cvt-muted">No published listings found.</div>'
							);
						} else {
							$.each(res.data, function (i, listing) {
								var thumb = listing.thumbnail
									? '<img src="' + escHtml(listing.thumbnail) + '" class="cvt-suggestion-thumb" alt="">'
									: '<span class="cvt-suggestion-thumb cvt-suggestion-thumb--placeholder"></span>';
								var price = listing.price
									? ' <span class="cvt-suggestion-price">' + escHtml(formatKes(listing.price)) + '</span>'
									: '';
								var cat = listing.category
									? ' <span class="cvt-muted">· ' + escHtml(listing.category) + '</span>'
									: '';

								var $row = $(
									'<div class="cvt-suggestion-item cvt-listing-suggestion">' +
									thumb +
									'<div class="cvt-suggestion-body">' +
									'<strong>' + escHtml(listing.title) + '</strong>' +
									price + cat +
									'</div></div>'
								);

								$row.on('click', function () {
									selectListing(listing);
								});

								$listingSugg.append($row);
							});
						}
						$listingSugg.removeAttr('hidden').show();
					}
				});
			}, 280);
		});

		// Keyboard navigation in results.
		$listingSearch.on('keydown', function (e) {
			var $items  = $listingSugg.find('.cvt-listing-suggestion');
			var $active = $items.filter('.is-active');
			if (e.key === 'ArrowDown') {
				e.preventDefault();
				($active.length ? $active.removeClass('is-active').next() : $items.first()).addClass('is-active');
			} else if (e.key === 'ArrowUp') {
				e.preventDefault();
				$active.removeClass('is-active').prev().addClass('is-active');
			} else if (e.key === 'Enter' && $active.length) {
				e.preventDefault();
				$active.trigger('click');
			} else if (e.key === 'Escape') {
				$listingSugg.hide().empty();
			}
		});

		// Close dropdown on outside click.
		$(document).on('click', function (e) {
			if (!$(e.target).closest('#cvt-listing-search-wrap').length) {
				$listingSugg.hide();
			}
		});

		// "Change" button — clear selection and go back to search.
		$('#cvt-listing-change').on('click', clearListing);
	}

	function selectListing(listing) {
		// Populate hidden form inputs.
		$('#cvt-field-title').val(listing.title);
		$('#cvt-field-description').val(listing.excerpt || '');
		$('#cvt-field-selling-price').val(listing.price || '0');
		$('#cvt-field-category').val(listing.category || '');
		$('#cvt-field-listing-url').val(listing.url);

		// Populate preview card.
		var $thumb = $('#cvt-listing-thumb');
		if (listing.thumbnail) {
			$thumb.attr('src', listing.thumbnail).removeAttr('hidden').show();
		} else {
			$thumb.hide();
		}
		$('#cvt-listing-preview-title').text(listing.title);
		$('#cvt-listing-preview-url').attr('href', listing.url);

		if (listing.price) {
			$('#cvt-listing-preview-price').text(formatKes(listing.price));
		}
		if (listing.category) {
			$('#cvt-listing-preview-category').text(listing.category);
		}

		// Show preview, hide search input.
		$listingSugg.hide().empty();
		$('#cvt-listing-search-state').hide();
		$('#cvt-listing-selected').removeAttr('hidden').show();

		// Fetch payout calculation for the listing price.
		if (listing.price && parseFloat(listing.price) > 0) {
			$.ajax({
				url:    CVT.ajax_url,
				method: 'GET',
				data:   { action: 'cvt_payout_preview', nonce: CVT.nonce, price: listing.price },
				success: function (res) {
					if (res.success) {
						$('#preview-commission-add').text(res.data.formatted.commission);
						$('#preview-payout-add').text(res.data.formatted.payout);
					}
				}
			});
		}
	}

	function clearListing() {
		$('#cvt-field-title').val('');
		$('#cvt-field-description').val('');
		$('#cvt-field-selling-price').val('0');
		$('#cvt-field-category').val('');
		$('#cvt-field-listing-url').val('');

		$listingSearch.val('').focus();
		$('#cvt-listing-preview-title').text('');
		$('#cvt-listing-preview-price').text('');
		$('#cvt-listing-preview-category').text('');
		$('#cvt-listing-preview-url').attr('href', '#');
		$('#preview-commission-add').text('—');
		$('#preview-payout-add').text('—');

		$('#cvt-listing-selected').hide();
		$('#cvt-listing-search-state').show();
	}

	// -------------------------------------------------------------------------
	// 2. Vendor Typeahead (#cvt-vendor-search)
	// -------------------------------------------------------------------------
	var $vendorSearch  = $('#cvt-vendor-search');
	var $vendorId      = $('#vendor_id');
	var $vendorSugg    = $('#cvt-vendor-suggestions');
	var vendorTimer    = null;
	var vendorSelected = '';   // label of the chosen vendor, to detect re-typing

	if ($vendorSearch.length) {
		$vendorSearch.wrap('<div id="cvt-vendor-search-wrap" style="position:relative;"></div>');

		$vendorSearch.on('input', function () {
			clearTimeout(vendorTimer);
			var q = $(this).val().trim();

			// If the agent types something different from the selected label, clear the ID.
			if (q !== vendorSelected) {
				$vendorId.val('');
			}

			if (q.length < 2) {
				$vendorSugg.hide().empty();
				return;
			}

			vendorTimer = setTimeout(function () {
				$.ajax({
					url:    CVT.ajax_url,
					method: 'GET',
					data:   { action: 'cvt_vendor_search', nonce: CVT.nonce, q: q },
					success: function (res) {
						$vendorSugg.empty();
						if (!res.success || !res.data.length) {
							$vendorSugg.append('<div class="cvt-suggestion-item cvt-muted">No vendors found.</div>');
						} else {
							$.each(res.data, function (i, v) {
								var $row = $(
									'<div class="cvt-suggestion-item" data-id="' + v.id + '">' +
									'<strong>' + escHtml(v.name) + '</strong>' +
									'<span class="cvt-suggestion-phone">' + escHtml(v.phone_primary) + '</span>' +
									'</div>'
								);
								$row.on('click', function () {
									vendorSelected = v.name + ' (' + v.phone_primary + ')';
									$vendorSearch.val(vendorSelected);
									$vendorId.val(v.id);
									$vendorSugg.hide().empty();
								});
								$vendorSugg.append($row);
							});
						}
						$vendorSugg.removeAttr('hidden').show();
					}
				});
			}, 280);
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
				e.preventDefault();
				$active.trigger('click');
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
	// 3. Live Payout Preview (edit mode — #selling_price)
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

		if (mediaFrame) {
			mediaFrame.open();
			return;
		}

		mediaFrame = wp.media({
			title:    CVT.i18n.select_image,
			button:   { text: CVT.i18n.use_image },
			multiple: true,
			library:  { type: 'image' }
		});

		mediaFrame.on('select', function () {
			var attachments = mediaFrame.state().get('selection').toJSON();
			$.each(attachments, function (i, att) {
				var thumb = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
				var $thumb = $(
					'<div class="cvt-image-thumb" data-new-id="' + att.id + '">' +
					'<img src="' + escHtml(thumb) + '" alt="">' +
					'<button type="button" class="cvt-image-remove" title="Remove">×</button>' +
					'</div>'
				);
				$('#cvt-image-grid').append($thumb);
				newImageIds.push(att.id);
				$('#cvt_image_ids').val(newImageIds.join(','));
			});
		});

		mediaFrame.open();
	});

	// -------------------------------------------------------------------------
	// 5. Image Removal (AJAX for saved images, DOM-only for new uploads)
	// -------------------------------------------------------------------------
	$(document).on('click', '.cvt-image-remove', function () {
		var $thumb = $(this).closest('.cvt-image-thumb');
		var rowId  = $thumb.data('row-id');
		var newId  = $thumb.data('new-id');
		var itemId = $thumb.data('item-id');

		if (newId) {
			// Not yet saved — just remove from DOM and pending ID list.
			newImageIds = newImageIds.filter(function (id) { return id !== newId; });
			$('#cvt_image_ids').val(newImageIds.join(','));
			$thumb.remove();
			return;
		}

		if (!rowId || !itemId) { return; }

		$.ajax({
			url:    CVT.ajax_url,
			method: 'POST',
			data: {
				action:       'cvt_remove_item_image',
				nonce:        CVT.nonce,
				item_id:      itemId,
				image_row_id: rowId
			},
			success: function (res) {
				if (res.success) {
					$thumb.remove();
				} else {
					alert(res.data && res.data.message ? res.data.message : 'Could not remove image.');
				}
			}
		});
	});

	// -------------------------------------------------------------------------
	// 6. Confirm Destructive Actions
	// -------------------------------------------------------------------------
	$(document).on('click', '.cvt-delete-link', function (e) {
		if (!confirm(CVT.i18n.confirm_delete)) {
			e.preventDefault();
		}
	});

})(jQuery);
