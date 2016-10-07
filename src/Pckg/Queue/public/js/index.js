(function (Vue, $, data) {

    Vue.component('pckg-queue-table', {
        template: '#pckg-queue-table',
        props: {
            table: {
                type: String,
                required: true,
                twoWay: true
            },
            queues: {
                type: Array,
                required: true,
                twoWay: true
            },
            url: {
                type: String,
                required: true,
                twoWay: true
            }
        },
        data: function () {
            return {};
        },
        ready: function () {
            setInterval(function () {
                http.getJSON(this.url, function (json) {
                    this.queues = json;
                }.bind(this));
            }.bind(this), 5000);
        },
        methods: {
            getClass: function (queue) {
                if (queue.status == 'failed_permanently') {
                    return 'danger';
                } else if (queue.status == 'failed') {
                    return 'warning';
                } else if (queue.status == 'running') {
                    return 'info';
                } else if (queue.status == 'started') {
                    return 'success';
                } else if (queue.status == 'created') {
                    return 'text-muted';
                }

                return '';
            }
        }
    });

    new Vue({
        el: '.pckg-queue-table',
        data: function () {
            return {
                currentQueue: data.currentQueue,
                nextQueue: data.nextQueue,
                prevQueue: data.prevQueue
            };
        }
    });

    /**
     * Charts.js
     */
    var ctx = document.getElementById("myChart");
    var myChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ["Red", "Blue", "Yellow", "Green", "Purple", "Orange"],
            datasets: [{
                label: '# of Votes',
                data: [12, 19, 3, 5, 2, 3],
                backgroundColor: [
                    'rgba(255, 99, 132, 0.2)',
                    'rgba(54, 162, 235, 0.2)',
                    'rgba(255, 206, 86, 0.2)',
                    'rgba(75, 192, 192, 0.2)',
                    'rgba(153, 102, 255, 0.2)',
                    'rgba(255, 159, 64, 0.2)'
                ],
                borderColor: [
                    'rgba(255,99,132,1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 159, 64, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                yAxes: [{
                    ticks: {
                        beginAtZero:true
                    }
                }]
            }
        }
    });

})
(Vue, jQuery, data);