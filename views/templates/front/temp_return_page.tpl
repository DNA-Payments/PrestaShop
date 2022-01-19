<style>
body {
    display: flex;
    width: 100%;
    height: 100vh;
    justify-content: center;
    align-items: center;
}

.dnapayments-loader {
    border: 8px solid #f3f3f3;
    border-radius: 50%;
    border-top: 8px solid #989898;
    width: 60px;
    height: 60px;
    -webkit-animation: dna-spin 2s linear infinite; /* Safari */
    animation: dna-spin 2s linear infinite;
}

/* Safari */
@-webkit-keyframes dna-spin {
    0% { -webkit-transform: rotate(0deg); }
    100% { -webkit-transform: rotate(360deg); }
}

@keyframes spdna-spinin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<div class="dnapayments-loader"></div>

<script
    src="https://code.jquery.com/jquery-3.5.0.min.js"
    integrity="sha256-xNzN2a4ltkB44Mc/Jz3pT4iU1cmeR0FkXs4pru/JxaQ="
    crossorigin="anonymous"
></script>
<script>

function showCustomError(txt) {
    console.error(txt);
    window.location.href = window.location.origin;
}

var count = 5;

function check() {
    count--;
    $.ajax({
        url : `{$check_url}?id_cart={$id_cart}&status={$status}`,
        type : 'GET',
        cache : false,
        success : function (result) {
            try {
                const data = JSON.parse(result);
                if (data.isCompleted) {
                    window.location.href = data.link;
                }
            } catch (e) {
                showCustomError('System error! Please try later');
            }
        },
        error: function (xhr) {
            showCustomError(xhr.responseText);
        }
    });
}

function timer() {
    if (count < 0) {
        window.location.href = window.location.origin;
        return;
    }

    check();
    setTimeout(function() {
        timer();
    }, 1000);
}

timer();

</script>