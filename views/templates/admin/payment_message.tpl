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
                    <strong>{l s='Cart ID:'}</strong>
                </td>
                <td>
                    {$data.id_cart|escape:'html':'utf-8'}
                </td>
            </tr>
        </table>
    </div>
</div>
