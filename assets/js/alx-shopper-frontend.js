jQuery(document).ready(function($) {
    if ($('.alx-shopper-results').length) {
        $('.alx-shopper-form').on('submit', function(e) {
            console.log('submitted');
            e.preventDefault();
            var $form = $(this);
            var data = $form.serialize();
            data += '&action=alx_shopper_filter';

            $form.find('.alx-shopper-spinner').show();
            $form.find('.alx-shopper-results').html('');
            $form.find('.alx-shopper-message').html('');
            $form.find('.alx-shopper-email-box').remove();

            $.post(alxShopperAjax.ajaxurl, data)
                .done(function(response) {
                    let html = '';
                    if (response.success && response.data.results.length) {
                        $.each(response.data.results, function(i, product) {
                            html += `
    <div class="alx-shopper-product">
        <a href="${product.permalink}">
            <img src="${product.image}" alt="${product.title}">
            <h3>${product.title}</h3>
        </a>
        <div class="alx-shopper-price">${product.price_html}</div>
        <div class="alx-shopper-explanation" style="margin-top:8px;font-size:0.95em;color:#2196F3;">${product.explanation}</div>
        <div class="alx-shopper-actions" style="margin-top:10px;">
            <button class="quick-view" data-id="${product.id}">Quick View</button>
            <a class="view-product" href="${product.permalink}" target="_blank">View Product</a>
        </div>
    </div>
`;
                        });
                        $form.find('.alx-shopper-results').html(html);
                    } else {
                        $form.find('.alx-shopper-results').html('<p>No products found.</p>');
                    }
                    // Show the message above results
                    $form.find('.alx-shopper-message').html(`<div class="alx-shopper-relax-msg" style="background:#ffeeba;color:#856404;padding:12px 18px;border-radius:6px;margin-bottom:18px;font-weight:bold;">${response.data.message}</div>`);

                    // Trigger event to insert email box above results, pass the form as data
                    $form.trigger('alxShopperResultsLoaded');

                    // Store results globally after AJAX filter
                    window.lastShopperResults = response.data.results;
                })
                .fail(function() {
                    $form.find('.alx-shopper-results').html('<p class="alx-shopper-error">Sorry, something went wrong. Please try again.</p>');
                })
                .always(function() {
                    $form.find('.alx-shopper-spinner').hide();
                });
        });

        function insertEmailBoxIfNeeded($form) {
            if (!window.alxShopperAjax || !alxShopperAjax.enable_email_results) return;
            if ($form.find('#alx-shopper-email').length) return; // Already exists

            var messageDiv = $form.find('.alx-shopper-message')[0];
            if (messageDiv) {
                var emailBox = document.createElement('div');
                emailBox.className = 'alx-shopper-email-box';
                emailBox.style.marginBottom = '20px';
                emailBox.innerHTML = `
                    <label for="alx-shopper-email"><strong>Email results to:</strong></label>
                    <input type="email" id="alx-shopper-email" name="alx-shopper-email" placeholder="your@email.com" style="margin-left:10px;" />
                    <button type="button" id="alx-shopper-send-email">Send</button>
                    <span id="alx-shopper-email-status" style="margin-left:10px;"></span>
                `;
                messageDiv.parentNode.insertBefore(emailBox, messageDiv.nextSibling);
            }
        }

        // Insert email box after results are loaded, scoped to the form
        $(document).on('alxShopperResultsLoaded', '.alx-shopper-form', function() {
            insertEmailBoxIfNeeded($(this));
        });

        // Email send handler, scoped to the form
        $(document).on('click', '#alx-shopper-send-email', function() {
            var $form = $(this).closest('.alx-shopper-form');
            var email = $form.find('#alx-shopper-email').val();
            var $status = $form.find('#alx-shopper-email-status');
            $status.text('');
            if (!email || !/^[^@]+@[^@]+\.[^@]+$/.test(email)) {
                $status.css('color', 'red').text('Please enter a valid email address.');
                return;
            }
            var data = $form.serialize();
            data += '&action=alx_shopper_send_results_email';
            data += '&email=' + encodeURIComponent(email);

            $form.find('#alx-shopper-send-email').prop('disabled', true);
            $status.css('color', '#333').text('Sending...');
            $.post(alxShopperAjax.ajaxurl, data, function(response) {
                if (response.success) {
                    $status.css('color', 'green').text('Email sent!');
                } else {
                    $status.css('color', 'red').text(response.data && response.data.message ? response.data.message : 'Failed to send email.');
                }
                $form.find('#alx-shopper-send-email').prop('disabled', false);
            }).fail(function() {
                $status.css('color', 'red').text('Failed to send email.');
                $form.find('#alx-shopper-send-email').prop('disabled', false);
            });
        });

        // Modal HTML (append once to body)
        if (!$('#alx-quick-view-modal').length) {
            $('body').append(`
                <div id="alx-quick-view-modal">
                    <div class="alx-quick-view-content">
                        <span class="alx-quick-view-close">&times;</span>
                        <div class="alx-quick-view-body"></div>
                    </div>
                </div>
            `);
        }

        // Quick View button handler (AJAX version)
        jQuery(document).on('click', '.quick-view', function() {
            var productId = jQuery(this).data('id');
            jQuery.ajax({
                url: alxShopperAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'alx_quick_view',
                    product_id: productId
                },
                beforeSend: function() {
                    // Show loading spinner/modal
                },
                success: function(response) {
                    // Insert response HTML into modal and show it
                },
                error: function() {
                    // Show error message
                }
            });
        });

        // Close modal when clicking close button or overlay (but not modal content)
        $(document).on('click', '.alx-quick-view-close, #alx-quick-view-modal', function(e) {
            if (
                e.target.id === 'alx-quick-view-modal' ||
                $(e.target).hasClass('alx-quick-view-close')
            ) {
                $('#alx-quick-view-modal').css('display', 'none');
            }
        });

        // Prevent modal close when clicking inside modal content
        $(document).on('click', '.alx-quick-view-content', function(e) {
            e.stopPropagation();
        });

        // Overlay close for results overlay
        $(document).on('click', '#alx-shopper-results-close, #alx-shopper-results-overlay', function(e) {
            if (e.target.id === 'alx-shopper-results-overlay' || e.target.id === 'alx-shopper-results-close') {
                $('#alx-shopper-results-overlay').fadeOut(150);
            }
        });
        $('.alx-shopper-results').on('click', function(e) {
            e.stopPropagation();
        });
    }
});

