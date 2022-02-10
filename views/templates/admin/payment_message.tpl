<div id="dnapayments_order_info" class="card mt-2">
    <div class="card-header">
        <h3 class="card-header-title">
            {l s='DNA Payments'}
        </h3>
    </div>
    <div class="card-body">
        <table class="table">
            <tr>
                <td>
                    <strong>{l s='DNA Payments order ID:'}</strong>
                </td>
                <td>
                    {$data.dnaOrderId|escape:'html':'utf-8'}
                </td>
            </tr>
            <tr>
                <td>
                    <strong>{l s='Transaction ID:'}</strong>
                </td>
                <td>
                    {$data.id_transaction|escape:'html':'utf-8'}
                </td>
            </tr>
            <tr>
                <td>
                    <strong>{l s='RRN:'}</strong>
                </td>
                <td>
                    {$data.rrn|escape:'html':'utf-8'}
                </td>
            </tr>
            <tr>
                <td>
                    <strong>{l s='Cart ID:'}</strong>
                </td>
                <td>
                    {$data.id_cart|escape:'html':'utf-8'}
                </td>
            </tr>
            <tr>
                <td>
                    <strong>{l s='Payment method:'}</strong>
                </td>
                <td>
                    {$data.payment_method|escape:'html':'utf-8'}
                </td>
            </tr>
            {if $data.payment_method eq 'paypal'}
                <tr>
                    <td>
                        <strong>{l s='PayPal status:'}</strong>
                    </td>
                    <td>
                        {$data.paypal_status|escape:'html':'utf-8'}
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong>{l s='PayPal capture status:'}</strong>
                    </td>
                    <td>
                        {$data.paypal_capture_status|escape:'html':'utf-8'}
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong>{l s='PayPal capture status reason:'}</strong>
                    </td>
                    <td>
                        {$data.paypal_capture_status_reason|escape:'html':'utf-8'}
                    </td>
                </tr>
            {/if}
        </table>
    </div>
</div>
