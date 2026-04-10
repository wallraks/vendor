/**
 * Corido Vendor Tracker — Admin JavaScript
 *
 * Handles:
 *  1. Vendor typeahead search in item form
 *  2. Live payout preview on selling price change
 *  3. WP Media uploader integration for item images
 *  4. Image removal via AJAX
 *  5. Confirm-before-delete for destructive links
 */
/* global CVT, wp */
(function ($) {
	'use strict';

	// -------------------------------------------------------------------------
	// 1. Vendor Typeahead
	// -------------------------------------------------------------------------
	var $vendorSearch     = $('#cvt-vendor-search');
	var $vendorIdInput    = $('#vendor_id');
	var $suggestions      = $('#cvt-vendor-suggestions');
	var typingTimer       = null;

	if ($vendorSearch.length) {
		// Wrap input in a positioned container for the dropdown.
		$vendorSearch.wrap('<div id="cvt-vendor-search-wrap" style="position:relative;"></div>');

		$vendorSearch.on('input', function () {
			clearTimeout(typingTimer);
			var q = $(this).val().trim();

			if (q.length < 2) {
				$suggestions.hide().empty();
				return;
			}

			typingTimer = setTimeout(function () {
				$.ajax({
					url:      CVT.ajax_url,
					method:   'GET',
					data:     { action: 'cvt_vendor_search', nonce: CVT.nonce, q: q },
					success: function (res) {
						$suggestions.empty();
						if (!res.success || !res.data.length) {
							$suggestions.append('<div class="cvt-suggestion-item cvt-muted">No vendors found.</div>');
						} else {
							$.each(res.data, function (i, v) {
								var $item = $(
									'<div class="cvt-suggestion-item" data-id="' + v.id + '">' +
									'<strong>' + escHtml(v.name) + '</strong>' +
									'<span class="cvt-suggestion-phone">' + escHtml(v.phone_primary) + '</span>' +
									'</div>'
								);
								$item.on('click', function () {
									$vendorSearch.val(v.name + ' (' + v.phone_primary + ')');
									$vendorIdInput.val(v.id);
									$suggestions.hide().empty();
								});
								$suggestions.append($item);
							});
						}
						$suggestions.removeAttr('hidden').show();
					}
				});
			}, 280);
		});

		// Keyboard navigation.
		$vendorSearch.on('keydown', function (e) {
			var $items = $suggestions.find('.cvt-suggestion-item');
			var $active = $items.filter('.is-active');
			if (e.key === 'ArrowDown') {
				e.preventDefault();
				if (!$active.length) {
					$items.first().addClass('is-active');
				} else {
					$active.removeClass('is-active').next().addClass('is-active');
				}
			} else if (e.key === 'ArrowUp') {
				e.preventDefault();
				$active.removeClass('is-active').prev().addClass('is-active');
			} else if (e.key === 'Enter' && $active.length) {
				e.preventDefault();
				$active.trigger('click');
			} else if (e.key === 'Escape') {
				$suggestions.hide().empty();
			}
		});

		// Click outside to close.
		$(document).on('click', function (e) {
			if (!$(e.target).closest('#cvt-vendor-search-wrap').length) {
				$suggestions.hide();
			}
		});

		// Clear vendor ID when user types again (so old selection isn't silently kept).
		$vendorSearch.on('input', function () {
			if ($(this).val() !== $(this).data('selected-label')) {
				$vendorIdInput.val('');
			}
		});
	}

	// -------------------------------------------------------------------------
	// 2. Live Payout Preview
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
				url:  CVT.ajax_url,
				method: 'GET',
				data: { action: 'cvt_payout_preview', nonce: CVT.nonce, price: price },
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
	// 3. WP Media Uploader — Add Images
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
				updateImageIdsInput();
			});
		});

		mediaFrame.open();
	});

	function updateImageIdsInput() {
		$('#cvt_image_ids').val(newImageIds.join(','));
	}

	// -------------------------------------------------------------------------
	// 4. Image Removal
	// -------------------------------------------------------------------------
	$(document).on('click', '.cvt-image-remove', function () {
		var $thumb      = $(this).closest('.cvt-image-thumb');
		var rowId       = $thumb.data('row-id');       // existing DB row
		var newId       = $thumb.data('new-id');       // freshly uploaded (not yet saved)
		var itemId      = $thumb.data('item-id');

		if (newId) {
			// Just remove from DOM and from the pending ID list.
			newImageIds = newImageIds.filter(function (id) { return id !== newId; });
			updateImageIdsInput();
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
	// 5. Confirm Destructive Actions
	// -------------------------------------------------------------------------
	$(document).on('click', '.cvt-delete-link', function (e) {
		if (!confirm(CVT.i18n.confirm_delete)) {
			e.preventDefault();
		}
	});

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

})(jQuery);
