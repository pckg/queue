$(document).ready(function () {

    var ctx = document.getElementById("myChart");
    var myChart = new Chart(ctx, {
        type: 'line',
        data: $('#myChart').data('chartjs')
    });

    (function (Vue, data, $) {

        new Vue({
            el: '#vue-app',
            data: function () {
                return {
                    currentQueue: data.currentQueue,
                    nextQueue: data.nextQueue,
                    prevQueue: data.prevQueue,
                    startedQueue: data.startedQueue
                };
            }
        });

    })(Vue, data, jQuery);

});