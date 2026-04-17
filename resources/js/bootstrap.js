const toastr = require("toastr");
window._ = require('lodash');
try {
    window.$ = window.jQuery = require('jquery');
} catch (e) {}

window.axios = require('axios');
window.toastr = toastr;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.drift = require('drift-zoom');
window.moment = require('moment');
