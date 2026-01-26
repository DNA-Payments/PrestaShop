{if isset($order_url)}

    <form action="{$order_url}" id="dna-payment-form" class="dna-payment-form" method="POST" style="display:none;">
        <input type="hidden" name="ajax" value="1" />
    </form>

    <script src="https://pay.dnapayments.com/checkout/payment-api.js"></script>

    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function () {
            var $ = window.jQuery;
            if (!$) {
                console.error('[DNA] jQuery not found');
                return;
            }

            var cards = {$cards|@json_encode nofilter};

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


            function attachDNAListener() {
                var $form = $('#dna-payment-form');
                var $btn  = $('#payment-confirmation button');

                if (!$form.length || !$btn.length) {
                    return;
                }

                $btn.off('click.dna').on('click.dna', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();

                    // manually trigger our submit
                    $form.trigger('submit');
                    return false;
                });

                $form.off('submit').on('submit', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();

                    $btn.prop('disabled', true);

                    $.ajax({
                        url: '{$order_url}',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            ajax: 1,
                            action: 'createOrder'
                        },
                        success: function (paymentData) {
                            if (paymentData.errors) {
                                showError(paymentData.errors);
                                $btn.prop('disabled', false);
                                return;
                            }

                            DNAPayments.configure({
                                isTestMode: '{$test_mode}' === '1',
                                cards: getCards()
                            });

                            if ('{$integration_type}' === 'embedded') {
                                DNAPayments.openPaymentIframeWidget(paymentData);
                            } else {
                                DNAPayments.openPaymentPage(paymentData);
                            }
                        },
                        error: function (xhr) {
                            showError(xhr.responseText || 'System error');
                            $btn.prop('disabled', false);
                        }
                    });

                    return false;
                });
            }


            function showError(error) {
                var $alert = $('<div class="alert alert-danger"></div>');
                if (Array.isArray(error)) {
                    var $ul = $('<ul></ul>');
                    error.forEach(function (e) {
                        $ul.append('<li>' + e + '</li>');
                    });
                    $alert.append($ul);
                } else {
                    $alert.text(error);
                }
                $('#checkout-payment-step').prepend($alert);
            }

            attachDNAListener();

            if (typeof prestashop !== 'undefined') {
                prestashop.on('updatedPaymentOptions', attachDNAListener);
            }
        });
    </script>

{/if}
