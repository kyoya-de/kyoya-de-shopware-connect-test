$(document).ready(function () {
    let oiOS = new Date().getTimezoneOffset();
    oiOS = (oiOS < 0 ? "+" : "-") + ("00" + parseInt((Math.abs(oiOS / 60)))).slice(-2);
    document.cookie = "oiLocalTimeZone=" + oiOS + ";path=/;";
});