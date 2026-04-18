/**
 * Corido Vendor Tracker — Admin JavaScript
 *
 * 1. Listivo listing search (add mode) — real-time, auto-fills visible form fields
 * 2. Vendor typeahead (add/edit)
 * 3. Live payout preview tied to the selling_price input
 * 4. WP Media uploader for item images
 * 5. Image removal via AJAX
 * 6. Confirm-before-delete
 * 7. Agreement file uploader (single file, image or PDF)
 * 8. Price-change note reveal (edit mode)
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
					error: function () {
						$listingSugg.html(
							'<div class="cvt-suggestion-item cvt-suggestion-empty">Search unavailable — check your connection.</div>'
						).removeAttr('hidden').show();
					},
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
	// 3. Live Payout Preview — tied to #selling_price, #commission_rate, #listing_fee
	// -------------------------------------------------------------------------
	var $sellingPrice        = $('#selling_price');
	var $commissionRate      = $('#commission_rate');
	var $dealType            = $('#deal_type');
	var $listingFeeInput     = $('#listing_fee');
	var $previewCommLabel    = $('#preview-commission-label');
	var $previewListingLabel = $('#preview-listing-fee-label');

	function cvtUpdatePayoutPreview() {
		var price = parseFloat($sellingPrice.val()) || 0;
		if (price <= 0) {
			$('#preview-commission').text('KES 0.00');
			$('#preview-payout').text('KES 0.00');
			return;
		}
		var dealType = $dealType.length ? ($dealType.val() || 'consignment') : 'consignment';
		var ajaxData = { action: 'cvt_payout_preview', nonce: CVT.nonce, price: price, deal_type: dealType };
		if (dealType === 'listing') {
			ajaxData.listing_fee = parseFloat($listingFeeInput.val()) || 0;
		} else {
			ajaxData.commission_rate = $commissionRate.val();
		}
		$.ajax({
			url:    CVT.ajax_url,
			method: 'GET',
			data:   ajaxData,
			success: function (res) {
				if (res.success) {
					$('#preview-commission').text(res.data.formatted.commission);
					$('#preview-payout').text(res.data.formatted.payout);
					if (res.data.deal_type !== 'listing') {
						$('#preview-rate').text(res.data.commission_rate);
					}
				}
			}
		});
	}

	if ($sellingPrice.length) {
		$sellingPrice.on('input change', cvtUpdatePayoutPreview);
		cvtUpdatePayoutPreview();
	}
	if ($commissionRate.length) {
		$commissionRate.on('input change', cvtUpdatePayoutPreview);
	}
	if ($listingFeeInput.length) {
		$listingFeeInput.on('input change', cvtUpdatePayoutPreview);
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

	// -------------------------------------------------------------------------
	// 7. Agreement File Uploader — single file, images + PDF
	// -------------------------------------------------------------------------
	var agreementFrame;
	var $addAgreementBtn   = $('#cvt-add-agreement');
	var $agreementSelected = $('#cvt-agreement-selected');
	var $agreementAttId    = $('#cvt-agreement-att-id');

	if ($addAgreementBtn.length) {
		$addAgreementBtn.on('click', function (e) {
			e.preventDefault();
			if (agreementFrame) { agreementFrame.open(); return; }

			agreementFrame = wp.media({
				title:    'Select Consignment Agreement',
				button:   { text: 'Use this file' },
				multiple: false
			});

			agreementFrame.on('select', function () {
				var att = agreementFrame.state().get('selection').first().toJSON();
				$agreementAttId.val(att.id);
				var label = att.title || att.filename || 'Agreement';
				$agreementSelected.html(
					'<span class="dashicons dashicons-media-document"></span> ' +
					'<a href="' + escHtml(att.url) + '" target="_blank" rel="noopener" class="cvt-agreement-chip-link">' + escHtml(label) + ' ↗</a> ' +
					'<button type="button" id="cvt-agreement-remove" class="button button-small">Remove</button>'
				).show();
				$addAgreementBtn.hide();
			});

			agreementFrame.open();
		});

		$(document).on('click', '#cvt-agreement-remove', function () {
			$agreementAttId.val('');
			$agreementSelected.hide().empty();
			$addAgreementBtn.show();
		});
	}

	// -------------------------------------------------------------------------
	// 8. Price-change note reveal (edit mode)
	// -------------------------------------------------------------------------
	var $origPrice     = $('#cvt-original-price');
	var $priceNoteWrap = $('#cvt-price-note-wrap');

	if ($origPrice.length && $priceNoteWrap.length) {
		var originalPrice = parseFloat($origPrice.val()) || 0;
		$sellingPrice.on('input change', function () {
			var current = parseFloat($(this).val()) || 0;
			if (Math.abs(current - originalPrice) > 0.001) {
				$priceNoteWrap.show();
			} else {
				$priceNoteWrap.hide();
			}
		});
	}

	// -------------------------------------------------------------------------
	// 9. Deal type toggle — show commission rate OR listing fee field
	// -------------------------------------------------------------------------
	function cvtApplyDealType(type) {
		var isListing = (type === 'listing');
		$('#cvt-commission-rate-wrap').toggle(!isListing);
		$('#cvt-listing-fee-wrap').toggle(isListing);
		$previewCommLabel.toggle(!isListing);
		$previewListingLabel.toggle(isListing);
		cvtUpdatePayoutPreview();
	}

	if ($dealType.length) {
		$dealType.on('change', function () {
			cvtApplyDealType($(this).val());
		});
		// Initialise for edit mode where deal_type may already be 'listing'.
		cvtApplyDealType($dealType.val());
	}

})(jQuery);
