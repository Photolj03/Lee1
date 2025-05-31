document.addEventListener('DOMContentLoaded', function() {
    // For each filter form on the page
    document.querySelectorAll('.alx-shopper-form').forEach(function(searchForm) {
        const resultsContainer = searchForm.querySelector('.alx-shopper-results');
        const messageContainer = searchForm.querySelector('.alx-shopper-message');

        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // Collect selected filters and categories from this form
            const formData = new FormData(searchForm);
            searchForm.querySelectorAll('.alx-attribute-dropdown').forEach(drop => {
                formData.append(drop.name + '_label', drop.getAttribute('data-label') || drop.name);
            });
            formData.append('action', 'alx_shopper_filter');

            // Optional: Show spinner
            const spinner = searchForm.querySelector('.alx-shopper-spinner');
            if (spinner) spinner.style.display = 'inline-block';

            fetch(alxShopperAjax.ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(response => {
                if (spinner) spinner.style.display = 'none';
                if (response.success) {
                    messageContainer.innerText = response.data.message;
                    resultsContainer.innerHTML = '';
                    // Store results on the form for email sending
                    searchForm.lastShopperResults = response.data.results;
                    if (response.data.results.length > 0) {
                        resultsContainer.innerHTML += `<div class="alx-shopper-relax-msg" style="background:#ffeeba;color:#856404;padding:12px 18px;border-radius:6px;margin-bottom:18px;font-weight:bold;">${response.data.message}</div>`;
                        response.data.results.forEach(product => {
                            resultsContainer.innerHTML += `
                                <div class="alx-shopper-product">
                                    <img src="${product.image}" alt="${product.title}">
                                    <h3>${product.title}</h3>
                                    <div class="alx-shopper-price">${product.price_html || ''}</div>
                                    <div class="alx-shopper-explanation" style="margin-top:8px;font-size:0.95em;color:#2196F3;">${product.explanation || ''}</div>
                                    <button class="quick-view" data-id="${product.id}">Quick View</button>
                                    <a href="${product.permalink}" class="view-product" target="_blank">View Product</a>
                                </div>
                            `;
                        });
                        // Add email results option if enabled
                        if (alxShopperAjax.enable_email_results) {
                            resultsContainer.innerHTML += `
                                <form class="alx-email-results-form" style="margin-top:24px;">
                                    <label><strong>Email these results to yourself:</strong></label>
                                    <input type="email" class="alx-email-results-input" required placeholder="Your email" style="margin-left:10px;">
                                    <button type="submit">Send</button>
                                    <span class="alx-email-results-status" style="margin-left:10px;"></span>
                                </form>
                            `;
                        }
                    } else {
                        resultsContainer.innerHTML = '<p>No products found.</p>';
                    }
                } else {
                    resultsContainer.innerHTML = '<p>No products found.</p>';
                }
            });
        });

        // Quick view functionality for this form
        resultsContainer.addEventListener('click', function(event) {
            if (event.target.classList.contains('quick-view')) {
                const productId = event.target.getAttribute('data-id');
                showQuickView(productId);
            }
        });

        // Email results handler for this form
        resultsContainer.addEventListener('submit', function(e) {
            if (e.target && e.target.classList.contains('alx-email-results-form')) {
                e.preventDefault();
                const email = e.target.querySelector('.alx-email-results-input').value;
                const status = e.target.querySelector('.alx-email-results-status');
                status.textContent = 'Sending...';
                fetch(alxShopperAjax.ajaxurl, {
                    method: 'POST',
                    body: new URLSearchParams({
                        action: 'alx_shopper_email_results',
                        email: email,
                        results: JSON.stringify(searchForm.lastShopperResults || [])
                    })
                })
                .then(res => res.json())
                .then(res => {
                    status.textContent = res.success ? 'Email sent!' : 'Failed to send email.';
                });
            }
        });
    });

    function showQuickView(productId) {
        // Find product data from the last results
        let product = null;
        // Find the form that triggered this (assume only one open modal at a time)
        document.querySelectorAll('.alx-shopper-form').forEach(form => {
            if (form.lastShopperResults) {
                product = form.lastShopperResults.find(p => String(p.id) === String(productId));
            }
        });

        // Modal HTML (append once to body)
        let modal = document.getElementById('alx-quick-view-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'alx-quick-view-modal';
            modal.style.display = 'none';
            modal.innerHTML = `
                <div class="alx-quick-view-content">
                    <span class="alx-quick-view-close" style="position:absolute;top:14px;right:18px;font-size:30px;cursor:pointer;">&times;</span>
                    <div class="alx-quick-view-body"></div>
                </div>
            `;
            document.body.appendChild(modal);

            // Close modal on overlay or close button click
            modal.addEventListener('click', function(e) {
                if (e.target === modal || e.target.classList.contains('alx-quick-view-close')) {
                    modal.style.display = 'none';
                }
            });
            // Prevent modal close when clicking inside modal content
            modal.querySelector('.alx-quick-view-content').addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }

        // Fill modal content
        const content = modal.querySelector('.alx-quick-view-body');
        if (product) {
            content.innerHTML = `
                <h2>${product.title}</h2>
                <img src="${product.image}" alt="${product.title}" style="max-width:100%;border-radius:12px;margin-bottom:20px;">
                <div class="alx-quick-view-price">${product.price_html || ''}</div>
                <div class="alx-quick-view-explanation">${product.explanation || ''}</div>
                <a href="${product.permalink}" target="_blank" class="button">View Full Product</a>
            `;
        } else {
            content.innerHTML = '<div style="text-align:center;padding:40px 0;">Product not found.</div>';
        }

        // Show modal (use flex for centering if your CSS expects it)
        modal.style.display = 'flex';
    }

    jQuery(document).on('click', '.quick-view', function(e) {
        e.preventDefault();
        var productId = jQuery(this).data('id');
        var $modal = jQuery('#alx-quick-view-modal');
        var $body = $modal.find('.alx-quick-view-body');
        $body.html('Loading...');
        $modal.show();

        jQuery.ajax({
            url: alxShopperAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'alx_quick_view',
                product_id: productId
            },
            success: function(response) {
                $body.html(response);
            },
            error: function(xhr, status, error) {
                $body.html('Error loading product.');
            }
        });
    });

    // Optional: Close modal on click
    jQuery(document).on('click', '.alx-quick-view-close', function() {
        jQuery('#alx-quick-view-modal').hide();
    });
});