{if isset($order_url) }
    <form style="display:none" class="dnapayment-payments-form" method="POST" />
    <script
        src="https://code.jquery.com/jquery-3.5.0.min.js"
        integrity="sha256-xNzN2a4ltkB44Mc/Jz3pT4iU1cmeR0FkXs4pru/JxaQ="
        crossorigin="anonymous"
    />
    <script src="https://pay.dnapayments.com/checkout/payment-api.js" />
    <script>
        $(document).ready(function() {
            var form = $('.dnapayment-payments-form');
            window.DNAPayments.configure({
                isTestMode: isTestMode()
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
                            var orderInfo = JSON.parse(result);
                            if(orderInfo.errors) {
                                return showCustomError(orderInfo.errors)
                            }
                            window.DNAPayments.openPaymentPage(getPaymentData(orderInfo));
                        } catch (e) {
                            return showCustomError('System error! Please try later')
                        }
                    },
                    error: function (xhr) {
                        return showCustomError(xhr.responseText)
                    }
                });
            });

            const getPaymentData = (orderInfo) => ({
                backLink: orderInfo.backLink,
                failureBackLink: `{$failureBackLink}`,
                postLink: `{$postLink}`,
                failurePostLink: `{$failurePostLink}`,
                language: 'eng',
                description: `{$gateway_order_description}`,
                accountId: orderInfo.accountId ? orderInfo.accountId : '',
                accountCountry: orderInfo.country,
                accountCity: orderInfo.city,
                accountStreet1: orderInfo.address1,
                accountEmail: orderInfo.email,
                accountFirstName: orderInfo.firstname,
                accountLastName: orderInfo.lastname,
                accountPostalCode: orderInfo.postcode,
                invoiceId:  orderInfo.orderId.toString(),
                terminal: getTerminalId(),
                amount: orderInfo.amount.toString(),
                currency: orderInfo.currency.toString(),
                transactionType: 'SALE',
                auth: orderInfo.auth
            });

            function isTestMode() {
                return `{$test_mode}` === '1'
            }

            function getTerminalId() {
                return isTestMode() ? `{$test_terminal_id}` :  `{$terminal_id}`;
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
