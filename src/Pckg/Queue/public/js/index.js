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
                    this.queues = json.queues;
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

})
(Vue, jQuery, data);