document.addEventListener('DOMContentLoaded', (event) => {
    var targetUrl = window.location.href;
    var redirectInput = document.querySelectorAll('.fst_login_wrapper input[name=redirect_to]');
    if(redirectInput && redirectInput.length) {
        redirectInput[0].value = targetUrl;
    }
});


