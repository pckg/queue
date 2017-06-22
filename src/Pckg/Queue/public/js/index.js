$(document).ready(function () {

    var ctx = document.getElementById("myChart");
    var myChart = new Chart(ctx, {
        type: 'line',
        data: $('#myChart').data('chartjs')
    });

});