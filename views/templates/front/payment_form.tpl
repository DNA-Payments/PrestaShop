{if isset($order_url) }
    <form style="display:none" class="dnapayment-payments-form" method="POST" />
    <script
        src="https://code.jquery.com/jquery-3.5.0.min.js"
        integrity="sha256-xNzN2a4ltkB44Mc/Jz3pT4iU1cmeR0FkXs4pru/JxaQ="
        crossorigin="anonymous"
    />
    <script src="https://pay.dnapayments.com/checkout/payment-api.js" />

    {literal}
    <script type="text/javascript">
        var json = {/literal}{$cards|@json_encode nofilter};{literal}
        var cards = JSON.parse(json || '[]');

        window.getCards = function () {
            return cards.map(function (c) {
                return {
                    merchantTokenId: c.cardTokenId,
                    panStar: c.cardPanStarred,
                    cardSchemeId: c.cardSchemeId,
                    cardSchemeName: c.cardSchemeName,
                    cardName: c.cardAlias || c.cardholderName,
                    expiryDate: c.cardExpiryDate
                }
            })
        }
    </script>
    {/literal}

    <script>
        $(document).ready(function() {
            var form = $('.dnapayment-payments-form');
            var cards = getCards();

            window.DNAPayments.configure({
                isTestMode: isTestMode(),
                cards: cards
            });

            form.submit(function(e) {
                e.preventDefault();
                $.ajax({
                    url : `{$order_url}`,
                    type : 'POST',
                    cache : false,
                    data : {
                        ajax : true,
                        action : 'createOrder'
                    },
                    success : function (result) {
                        try {
                            var paymentData = JSON.parse(result);
                            if(paymentData.errors) {
                                return showCustomError(paymentData.errors)
                            }

                            if (`{$integration_type}` == 'embedded') {
                                window.DNAPayments.openPaymentIframeWidget(paymentData);
                            } else {
                                window.DNAPayments.openPaymentPage(paymentData);
                            }
                        } catch (e) {
                            return showCustomError('System error! Please try later')
                        }
                    },
                    error: function (xhr) {
                        return showCustomError(xhr.responseText)
                    }
                });
            });

            function isTestMode() {
                return `{$test_mode}` === '1'
            }
            
            function showCustomError(error) {
                var errorContent = document.createElement("p");
                if(Array.isArray(error)) {
                    errorContent.append(generateErrorList(error))
                } else {
                    errorContent.innerHTML = error;
                }
                errorContent.classList = 'alert alert-danger';
                $('#checkout-payment-step').prepend(errorContent)
            }

            function generateErrorList(errors) {
                const newList = document.createElement('ul');

                for (let i = 0; i < errors.length; i++) {
                    const item = document.createElement('li')
                    item.innerHTML = errors[i];
                    newList.append(item);
                }

                return newList;
            }
        });
    </script>

{/if}
