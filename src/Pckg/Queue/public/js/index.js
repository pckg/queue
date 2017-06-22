$(document).ready(function () {

    if ($('#myChart').length) {
        var ctx = document.getElementById("myChart");
        var myChart = new Chart(ctx, {
            type: 'line',
            data: $('#myChart').data('chartjs')
        });
    }

});