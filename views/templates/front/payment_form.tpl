{if isset($order_url)}

    <form action="{$order_url}" id="dna-payment-form" class="dna-payment-form" method="POST" style="display:none;">
        <input type="hidden" name="ajax" value="1" />
    </form>

    <script src="https://pay.dnapayments.com/checkout/payment-api.js" data-dna-payment-api="1"></script>

    <script type="text/javascript">
        (function () {
            'use strict';

            function ready(fn) {
                if (document.readyState !== 'loading') {
                    fn();
                } else {
                    document.addEventListener('DOMContentLoaded', fn);
                }
            }

            ready(function () {
                var cards = {$cards nofilter};
                if (!Array.isArray(cards)) {
                    cards = [];
                }

                function getCards() {
                    return cards.map(function (c) {
                        return {
                            merchantTokenId: c.cardTokenId,
                            panStar: c.cardPanStarred,
                            cardSchemeId: c.cardSchemeId,
                            cardSchemeName: c.cardSchemeName,
                            cardName: c.cardAlias || c.cardholderName,
                            expiryDate: c.cardExpiryDate
                        };
                    });
                }

                function loadDnaSdk() {
                    if (window.DNAPayments) {
                        return Promise.resolve(window.DNAPayments);
                    }

                    return new Promise(function (resolve, reject) {
                        var script = document.querySelector('script[data-dna-payment-api="1"]');

                        if (!script) {
                            script = document.createElement('script');
                            script.src = 'https://pay.dnapayments.com/checkout/payment-api.js';
                            script.async = false;
                            script.setAttribute('data-dna-payment-api', '1');
                            document.head.appendChild(script);
                        }

                        script.addEventListener('load', function () {
                            if (window.DNAPayments) {
                                resolve(window.DNAPayments);
                            } else {
                                reject(new Error('DNA Payments SDK did not initialize'));
                            }
                        });
                        script.addEventListener('error', function () {
                            reject(new Error('DNA Payments SDK could not be loaded'));
                        });
                    });
                }

                function findButton() {
                    // Core PrestaShop checkout "Place order" button.
                    return document.querySelector('#payment-confirmation button')
                        || document.querySelector('#payment-confirmation [type="submit"]');
                }

                function getDnaPaymentOptionId() {
                    var dnaSubmitButton = document.querySelector('#dna-payment-form button[id^="pay-with-payment-option-"]');
                    if (!dnaSubmitButton) {
                        return null;
                    }

                    return dnaSubmitButton.id.replace('pay-with-', '');
                }

                function isDnaPaymentSelected() {
                    var optionId = getDnaPaymentOptionId();
                    var option = optionId ? document.getElementById(optionId) : null;

                    return !!(option && option.checked);
                }

                function getErrorContainer() {
                    return document.querySelector('#checkout-payment-step')
                        || document.querySelector('#dna-payment-form').parentNode;
                }

                function clearErrors() {
                    var existing = document.querySelectorAll('.dna-payment-alert');
                    for (var i = 0; i < existing.length; i++) {
                        if (existing[i].parentNode) {
                            existing[i].parentNode.removeChild(existing[i]);
                        }
                    }
                }

                function showError(error) {
                    clearErrors();

                    var alert = document.createElement('div');
                    alert.className = 'alert alert-danger dna-payment-alert';

                    if (Array.isArray(error)) {
                        var ul = document.createElement('ul');
                        error.forEach(function (e) {
                            var li = document.createElement('li');
                            li.textContent = e;
                            ul.appendChild(li);
                        });
                        alert.appendChild(ul);
                    } else {
                        alert.textContent = error;
                    }

                    var container = getErrorContainer();
                    if (container) {
                        container.insertBefore(alert, container.firstChild);
                    }
                }

                var isSubmitting = false;

                function submitPayment(btn) {
                    if (isSubmitting) {
                        return;
                    }
                    isSubmitting = true;

                    function reEnable() {
                        isSubmitting = false;
                        if (btn) { btn.disabled = false; }
                    }

                    if (btn) {
                        btn.disabled = true;
                    }

                    fetch('{$order_url}', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'ajax=1&action=createOrder',
                        credentials: 'same-origin'
                    })
                        .then(function (response) {
                            return response.json();
                        })
                        .then(function (paymentData) {
                            if (paymentData.errors) {
                                showError(paymentData.errors);
                                reEnable();
                                return;
                            }

                            loadDnaSdk()
                                .then(function (DNAPayments) {
                                    DNAPayments.configure({
                                        isTestMode: '{$test_mode}' === '1',
                                        cards: getCards(),
                                        events: {
                                            cancelled: function () {
                                                reEnable();
                                            },
                                            declined: function () {
                                                reEnable();
                                            },
                                            paid: function () {
                                            }
                                        }
                                    });

                                    if ('{$integration_type}' === 'embedded') {
                                        DNAPayments.openPaymentIframeWidget(paymentData);
                                    } else {
                                        DNAPayments.openPaymentPage(paymentData);
                                    }
                                })
                                .catch(function () {
                                    showError('Payment form could not be loaded');
                                    reEnable();
                                });
                        })
                        .catch(function () {
                            showError('System error');
                            reEnable();
                        });
                }

                function onButtonClick(e) {
                    if (!isDnaPaymentSelected()) {
                        return true;
                    }

                    e.preventDefault();
                    e.stopPropagation();
                    if (typeof e.stopImmediatePropagation === 'function') {
                        e.stopImmediatePropagation();
                    }
                    submitPayment(findButton());
                    return false;
                }

                function onFormSubmit(e) {
                    if (!isDnaPaymentSelected()) {
                        return true;
                    }

                    e.preventDefault();
                    e.stopPropagation();
                    if (typeof e.stopImmediatePropagation === 'function') {
                        e.stopImmediatePropagation();
                    }
                    submitPayment(findButton());
                    return false;
                }

                function attachDNAListener() {
                    var btn = findButton();
                    var form = document.getElementById('dna-payment-form');
                    var dnaSubmitButton = form ? form.querySelector('button[id^="pay-with-payment-option-"]') : null;

                    if (!btn || !form || !dnaSubmitButton) {
                        return;
                    }
                    if (btn.getAttribute('data-dna-bound') === '1') {
                        if (form.getAttribute('data-dna-form-bound') === '1') {
                            return;
                        }
                    } else {
                        btn.setAttribute('data-dna-bound', '1');
                        btn.addEventListener('click', onButtonClick, true);
                    }

                    if (form.getAttribute('data-dna-form-bound') !== '1') {
                        form.setAttribute('data-dna-form-bound', '1');
                        form.onsubmit = onFormSubmit;
                        form.addEventListener('submit', onFormSubmit, true);

                        if (window.jQuery) {
                            window.jQuery(form).off('submit.dna').on('submit.dna', onFormSubmit);
                        }
                    }

                    if (dnaSubmitButton.getAttribute('data-dna-submit-bound') !== '1') {
                        dnaSubmitButton.setAttribute('data-dna-submit-bound', '1');
                        dnaSubmitButton.setAttribute('type', 'button');
                        dnaSubmitButton.addEventListener('click', onFormSubmit, true);
                    }
                }

                attachDNAListener();

                if (typeof prestashop !== 'undefined' && typeof prestashop.on === 'function') {
                    prestashop.on('updatedPaymentOptions', attachDNAListener);
                }
            });
        })();
    </script>

{/if}
