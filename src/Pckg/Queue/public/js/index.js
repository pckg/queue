(function (Vue, $, data) {

    var http = {

        formToData: function (vueElement, keys) {
            var data = {};

            $.each(keys, function (i, key) {
                data[key] = vueElement.form[key];
            });

            return data;
        },

        submitForm: function (vueElement, fields) {
            var data = http.formToData(vueElement, fields);
            var $form = $(vueElement.$el);
            var url = $form.attr('action');

            $.post(url, data, function (data) {

            }, 'JSON');
        },

        getJSON: function (url, whenDone) {
            $.ajax({
                url: url,
                dataType: 'JSON'
            }).done(function (data) {
                whenDone(data);
            });
        }

    };

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