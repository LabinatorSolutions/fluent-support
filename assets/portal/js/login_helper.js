/******/ (() => { // webpackBootstrap
/*!***************************************************!*\
  !*** ./resources/customer_portal/login_helper.js ***!
  \***************************************************/
document.addEventListener('DOMContentLoaded', function (event) {
  var targetUrl = window.location.href;
  var redirectInput = document.querySelectorAll('.fst_login_wrapper input[name=redirect_to]');

  if (redirectInput && redirectInput.length) {
    redirectInput[0].value = targetUrl;
  }
});
/******/ })()
;
//# sourceMappingURL=login_helper.js.map