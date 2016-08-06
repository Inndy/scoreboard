window.ajax = function (method, url, data)
{
    return new Promise(function (resolve, reject) {
        var xhr = new XMLHttpRequest();
        window.last_xhr = xhr;
        xhr.onreadystatechange = function() {
            if(xhr.readyState === 4 && xhr.status === 200) {
                resolve(xhr.response);
            } else {
                if(xhr.readyState < 0 || xhr.readyState >= 4) {
                    reject(xhr);
                }
            }
        };
        xhr.open(method, url);
        if(typeof(data) === 'object') {
            data = JSON.stringify(data);
            xhr.setRequestHeader('Content-Type', 'application/json');
        }
        xhr.send(data);
    });
}

function Task(data)
{
    var self = this;
    data = data || {};
    Task.fields.forEach(function (field) {
        self[field] = data[field] || '';
    });
}

Task.fields = ['type', 'name', 'ordering', 'points', 'link', 'flag', 'text'];

function TaskWrapper(data) {
    this.task = new Task(data);
}

TaskWrapper.prototype.save = function () {
    var self = this;

    return new Promise(function (resolve, reject) {
        ajax('POST', '/tasks', self.task).then(function(data) {
            self.task.id = JSON.parse(data).id;
            window.tasks.push(self.task);
            window.tasks.sort(function (a, b) { return a.ordering - b.ordering });
            resolve.apply(this, arguments);
        }, reject);
    });
};

Vue.component('task-view', {
    template: '#task',
    props: {
        task: {
            type: Object
        }
    },
    data: function () {
        return {fields: Task.fields}
    },
    created: function () {
        this.$on('save', this.save);
    },
    methods: {
        delete: function () {
            var self = this;
            var id = self.task.id;
            if(!confirm("Do you really want to delete task #" + id)) {
                return;
            }

            ajax('DELETE', '/tasks/' + id).then(function() {
                self.$dispatch('delete_task', self.task);
                alert('Success to delete task #' + id);
            }, function(xhr) {
                alert('Failed to delete task');
            });
        },
        save: function () {
            return ajax('PATCH', '/tasks/' + this.task.id, this.task);
        },
        edit: function () {
            this.$dispatch('edit_task', this);
        }
    }
});

Vue.component('task-editor', {
    template: '#task_editor',
    data: function () {
        return { task: new Task(), edit_item: null, fields: Task.fields };
    },
    methods: {
        copy: function (to, from) {
            for(var i in from) {
                to[i] = from[i];
            }
        },
        set_edit_item: function (item) {
            this.edit_item = item;
            this.copy(this.task, this.edit_item.task);
        },
        save: function () {
            this.copy(this.edit_item.task, this.task);
            this.edit_item.save();
            this.edit_item = null;
        },
        cancel: function () {
            this.edit_item = null;
        }
    }
});

Vue.filter('censored', function(value, size) {
    size = size || 0.5;

    var len = (value.length * size)|0;
    var off = ~~((value.length - len) / 2);

    var dots = new Array(len);
    dots.fill('.');
    return value.substring(0, off) + dots.join('') + value.substring(off + len);
});

var vm = new Vue({
    el: '#app',
    created: function () {
        var self = this;

        ajax('GET', '/tasks').then(function (data) {
            JSON.parse(data).data.forEach(function (task) {
                self.tasks.push(task);
            });
            window.tasks = self.tasks;
        });

        self.$on('delete_task', function (task) {
            self.tasks.$remove(task);
        });

        self.$on('edit_task', function (task) {
            self.$refs.editor.set_edit_item(task);
        });

        self.$watch('tasks', function () {
            self.tasks.forEach(function (task) {
                if(!self.task_type_set[task.type]) {
                    self.task_type_set[task.type] = 1;
                    self.types.push(task.type);
                    self.types.sort();
                }
            });
        });
    },
    methods: {
        add_task: function () {
            this.$refs.editor.set_edit_item(new TaskWrapper());
        }
    },
    data: {
        tasks: [],
        types: ['All'],
        task_type_set: {},
        filter: 'All',
        fields: Task.fields
    },
    computed: {
    }
});
