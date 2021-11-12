// document.addEventListener('DOMContentLoaded', (event) => {
//     var targetUrl = window.location.href;
//     var redirectInput = document.querySelectorAll('.fst_login_wrapper input[name=redirect_to]');
//     if(redirectInput && redirectInput.length) {
//         redirectInput[0].value = targetUrl;
//     }
// });
//

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('fstRegistrationForm');

    function toggleLoading(submitBtn) {
        submitBtn.classList.toggle('loading');
        submitBtn.disabled = !submitBtn.disabled;
    }

    if (form) {
        form.addEventListener('submit', (event) => {
            event.preventDefault();

            const submitBtn = document.getElementById('fst_submit');
            toggleLoading(submitBtn);

            document.querySelectorAll('.error.text-danger').forEach(e => {
                e.parentNode.parentNode.classList.remove('is-error');
                e.remove();
            })

            const data = new FormData(form);
            const request = new XMLHttpRequest();

            request.open('POST', window.fluentSupportPublic.signup, true);
            request.responseType = 'json';

            request.onload = function () {
                if (this.status === 200) {
                    if (this.response.redirect) {
                        window.location.replace(this.response.redirect);
                    }
                } else {
                    if (this.response.error) {
                        let el = document.createElement("div");
                        el.classList.add('error', 'text-danger');
                        el.innerHTML = this.response.error;

                        form.appendChild(el);
                    } else {
                        for (const property in this.response) {
                            const field = document.getElementById('fst_' + property);

                            if (field) {
                                let el = document.createElement("div");
                                el.classList.add('error', 'text-danger');
                                el.innerHTML = Object.values(this.response[property])[0];
                                field.parentNode.insertBefore(el, field.nextSibling);
                                field.parentNode.parentNode.classList.add('is-error');
                            }
                        }
                    }
                }

                toggleLoading(submitBtn);
            };

            request.send(data);
        })
    }
})
