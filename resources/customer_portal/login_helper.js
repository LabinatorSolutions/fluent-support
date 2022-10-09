document.addEventListener('DOMContentLoaded', () => {
    const registrationForm = document.getElementById('fstRegistrationForm');
    const resetPasswordForm = document.getElementById('fstResetPasswordForm');

    function toggleLoading(submitBtn) {
        submitBtn.classList.toggle('loading');
        submitBtn.disabled = !submitBtn.disabled;
    }

    if (registrationForm) {
        registrationForm.addEventListener('submit', (event) => {
            event.preventDefault();
            handleFormSubmission( registrationForm, 'fst_submit', window.fluentSupportPublic.signup );
        });
    }

    if (resetPasswordForm) {
        resetPasswordForm.addEventListener('submit', (event) => {
            event.preventDefault();
            handleFormSubmission( resetPasswordForm, 'fst_reset_pass', window.fluentSupportPublic.resetPass );
        } );
    }

    if(document.getElementById('fs_show_signup')) {
        document.getElementById('fs_show_signup').addEventListener('click', function (event) {
            fsToggleForms(event, this, '.fst_registration_wrapper');
        });
    }

    if(document.getElementById('fs_show_reset_password')) {
        document.getElementById('fs_show_reset_password').addEventListener('click', function (event) {
            fsToggleForms(event, this, '.fst_reset_pass_wrapper');
        });
    }

    if(document.getElementById('fs_show_login')) {
        document.getElementById('fs_show_login').addEventListener('click', function (event) {
            fsToggleForms(event, this, '.fst_login_wrapper');
        });
    }

    function handleFormSubmission( form, submitBtnId, reqUrl ) {
        const submitBtn = document.getElementById( submitBtnId );
        toggleLoading(submitBtn);

        document.querySelectorAll('.error.text-danger').forEach(e => {
            e.parentNode.parentNode.classList.remove('is-error');
            e.remove();
        })

        const data = new FormData(form);

        const request = new XMLHttpRequest();

        request.open('POST', reqUrl, true);
        request.responseType = 'json';
        request.setRequestHeader('X-WP-Nonce', window.fluentSupportPublic.nonce);

        request.onload = function () {
            if (this.status === 200) {
                if (this.response.redirect) {
                    window.location.href = this.response.redirect;
                } else if(this.response.message) {
                    let el = document.createElement("div");
                    el.classList.add('success', 'text-success');
                    el.innerHTML = this.response.message;
                    form.appendChild(el);
                } else {
                    window.location.reload();
                }
            } else {

                let genericError = this.response.error;

                if(!genericError && this.response.message) {
                    genericError = this.response.message;
                } else if (genericError && this.response.data.status === 403) {
                    genericError = this.response.message;
                }

                if (genericError) {
                    let el = document.createElement("div");
                    el.classList.add('error', 'text-danger');
                    el.innerHTML = genericError;

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
    }

    function fsToggleForms(event, that, target) {
        event.preventDefault();
        that.parentNode.parentNode.classList.toggle('hide');
        document.querySelector(target).classList.toggle('hide');
    }

    const loginForm = document.querySelectorAll('#fst_login_form form');

    if (loginForm && loginForm[0]) {
        const form = loginForm[0];
        form.addEventListener('submit', (event) => {
            event.preventDefault();

            const submitBtn = document.getElementById('wp-submit');
            toggleLoading(submitBtn);

            document.querySelectorAll('.error.text-danger').forEach(e => {
                e.parentNode.parentNode.classList.remove('is-error');
                e.remove();
            })

            const data = new FormData(loginForm[0]);
            data.append('_support_login_nonce', window.fluentSupportPublic.fsupport_login_nonce);

            const request = new XMLHttpRequest();

            request.open('POST', window.fluentSupportPublic.login, true);
            request.responseType = 'json';
            request.setRequestHeader('X-WP-Nonce', window.fluentSupportPublic.nonce);

            request.onload = function () {
                if (this.status === 200) {
                    if (this.response.redirect) {
                        window.location.href = this.response.redirect;
                        setTimeout(function () {
                            window.location.reload();
                        }, 1500);
                    } else {
                        window.location.href = window.fluentSupportPublic.redirect_fallback;
                    }
                } else {
                    let genericError = this.response.error;

                    if (this.response && this.status === 403) {
                        genericError = this.response.message;
                    }

                    if (genericError) {

                        let submitWrapper = document.getElementsByClassName('login-submit');
                        if(submitWrapper.length) {
                            submitWrapper = submitWrapper[0];
                        } else {
                            submitWrapper = form;
                        }

                        let el = document.createElement("div");
                        el.classList.add('error', 'text-danger');
                        el.innerHTML = genericError;

                        submitWrapper.parentNode.insertBefore(el, submitWrapper.nextSibling);
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
});
