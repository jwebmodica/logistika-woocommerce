/**
 * Logistika WooCommerce Admin JavaScript
 */

(function($) {
    'use strict';

    var Logistika = {

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Single order export via email (from meta box and table)
            $(document).on('click', '.logistika-export-btn', this.exportSingleOrder);
            $(document).on('click', '.logistika-single-export', this.exportSingleOrder);

            // Single order send via API (from meta box)
            $(document).on('click', '.logistika-api-send-btn', this.sendViaApi);

            // Preview button
            $(document).on('click', '.logistika-preview-btn', this.previewOrder);

            // Bulk export form
            $('#logistika-export-form').on('submit', this.handleBulkExport);

            // Select all checkbox
            $('#cb-select-all').on('change', this.toggleSelectAll);

            // Modal close
            $(document).on('click', '.logistika-modal-close', this.closeModal);
            $(document).on('click', '.logistika-modal', function(e) {
                if (e.target === this) {
                    Logistika.closeModal();
                }
            });

            // ESC key to close modal
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    Logistika.closeModal();
                }
            });

            // Test email button
            $('#logistika-test-email').on('click', this.sendTestEmail);
        },

        /**
         * Export single order via email
         */
        exportSingleOrder: function(e) {
            e.preventDefault();

            var $button = $(this);
            var orderId = $button.data('order-id');

            if (!orderId) {
                return;
            }

            if (!confirm(logistikaAdmin.strings.confirm_export)) {
                return;
            }

            $button.addClass('logistika-loading').prop('disabled', true);

            $.ajax({
                url: logistikaAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'logistika_export_order',
                    nonce: logistikaAdmin.nonce,
                    order_id: orderId
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        if ($button.closest('.logistika-meta-box').length) {
                            location.reload();
                        }
                    } else {
                        alert(response.data || logistikaAdmin.strings.error);
                    }
                },
                error: function() {
                    alert(logistikaAdmin.strings.error);
                },
                complete: function() {
                    $button.removeClass('logistika-loading').prop('disabled', false);
                }
            });
        },

        /**
         * Send single order via API
         */
        sendViaApi: function(e) {
            e.preventDefault();

            var $button = $(this);
            var orderId = $button.data('order-id');

            if (!orderId) {
                return;
            }

            if (!confirm(logistikaAdmin.strings.confirm_api)) {
                return;
            }

            $button.addClass('logistika-loading').prop('disabled', true);

            $.ajax({
                url: logistikaAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'logistika_send_via_api',
                    nonce: logistikaAdmin.nonce,
                    order_id: orderId
                },
                success: function(response) {
                    if (response.success) {
                        var msg = response.data.message;
                        if (response.data.logistika_id) {
                            msg += ' (ID: ' + response.data.logistika_id + ')';
                        }
                        alert(msg);
                        if ($button.closest('.logistika-meta-box').length) {
                            location.reload();
                        }
                    } else {
                        alert(response.data || logistikaAdmin.strings.error);
                    }
                },
                error: function() {
                    alert(logistikaAdmin.strings.error);
                },
                complete: function() {
                    $button.removeClass('logistika-loading').prop('disabled', false);
                }
            });
        },

        /**
         * Preview order CSV
         */
        previewOrder: function(e) {
            e.preventDefault();

            var $button = $(this);
            var orderId = $button.data('order-id');

            if (!orderId) {
                return;
            }

            $button.addClass('logistika-loading').prop('disabled', true);

            $.ajax({
                url: logistikaAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'logistika_preview_csv',
                    nonce: logistikaAdmin.nonce,
                    order_ids: [orderId]
                },
                success: function(response) {
                    if (response.success) {
                        Logistika.showModal(response.data.html);
                    } else {
                        alert(response.data || logistikaAdmin.strings.error);
                    }
                },
                error: function() {
                    alert(logistikaAdmin.strings.error);
                },
                complete: function() {
                    $button.removeClass('logistika-loading').prop('disabled', false);
                }
            });
        },

        /**
         * Handle bulk export form
         */
        handleBulkExport: function(e) {
            var action = $('#bulk-action-selector').val();
            var selectedOrders = $('input[name="order_ids[]"]:checked');

            if (selectedOrders.length === 0) {
                e.preventDefault();
                alert(logistikaAdmin.strings.select_order);
                return false;
            }

            if (action === 'preview') {
                e.preventDefault();

                var orderIds = [];
                selectedOrders.each(function() {
                    orderIds.push($(this).val());
                });

                $.ajax({
                    url: logistikaAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'logistika_preview_csv',
                        nonce: logistikaAdmin.nonce,
                        order_ids: orderIds
                    },
                    success: function(response) {
                        if (response.success) {
                            Logistika.showModal(response.data.html);
                        } else {
                            alert(response.data || logistikaAdmin.strings.error);
                        }
                    },
                    error: function() {
                        alert(logistikaAdmin.strings.error);
                    }
                });

                return false;
            }

            if (action === 'export_download') {
                e.preventDefault();

                var orderIds = [];
                selectedOrders.each(function() {
                    orderIds.push($(this).val());
                });

                // Download via window
                window.location.href = logistikaAdmin.ajaxUrl +
                    '?action=logistika_download_csv' +
                    '&nonce=' + logistikaAdmin.nonce +
                    '&order_ids=' + orderIds.join(',');

                return false;
            }

            // For export_email and export_api, let form submit normally
            if (!action) {
                e.preventDefault();
                alert(logistikaAdmin.strings.select_action);
                return false;
            }

            return true;
        },

        /**
         * Toggle select all
         */
        toggleSelectAll: function() {
            var isChecked = $(this).prop('checked');
            $('input[name="order_ids[]"]').prop('checked', isChecked);
        },

        /**
         * Show modal
         */
        showModal: function(content) {
            var $modal = $('#logistika-preview-modal');

            if ($modal.length === 0) {
                $modal = $('<div id="logistika-preview-modal" class="logistika-modal">' +
                    '<div class="logistika-modal-content">' +
                    '<span class="logistika-modal-close">&times;</span>' +
                    '<h2>Anteprima CSV</h2>' +
                    '<div id="logistika-preview-content"></div>' +
                    '</div></div>');
                $('body').append($modal);
            }

            $('#logistika-preview-content').html(content);
            $modal.fadeIn(200);
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('#logistika-preview-modal').fadeOut(200);
        },

        /**
         * Send test email (uses most recent order)
         */
        sendTestEmail: function(e) {
            e.preventDefault();

            var $button = $(this);
            var $result = $('#logistika-test-result');

            $button.prop('disabled', true);
            $result.html('<span class="spinner is-active" style="float:none;"></span>');

            $.ajax({
                url: logistikaAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'logistika_test_email',
                    nonce: logistikaAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<span class="logistika-success">' + response.data.message + '</span>');
                    } else {
                        $result.html('<span class="logistika-error">' + (response.data || 'Errore') + '</span>');
                    }
                },
                error: function() {
                    $result.html('<span class="logistika-error">Errore di connessione</span>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        Logistika.init();
    });

})(jQuery);
