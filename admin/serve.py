import os
from bottle import *
from db import *

path = os.path

STATIC_PATH = path.join(path.dirname(path.abspath(__file__)), 'static')
static_res = lambda name: path.join(STATIC_PATH, name)

def not_found(what):
    response.status = 404
    return {'error': 1, 'error_message': '%s not found' % what}

def not_found_object(type_, id_):
    return not_found('%s#%s' % (type_, id_))

app = Bottle()

@app.get('/')
def index():
    return open(static_res('index.html'), 'rb')

@app.get('/static/<path:path>')
def static(path):
    return static_file(path, static_res('.'))

@app.get('/tasks')
def list_tasks():
    return {'data': [ task.as_dict() for task in Task.select() ], 'error': 0}

@app.post('/tasks')
def add_task():
    kwargs = { k: v for k, v in request.json.items() if k in Task._meta.fields }
    task = Task(**kwargs)
    task.save()
    return {'error': 0, 'id': task.id}

@app.get('/tasks/<id:int>')
def get_task(id):
    task = Task.select().where(Task.id == id).get()
    if task == None: return not_found_object('task', id)
    return task.as_dict()

@app.patch('/tasks/<id:int>')
def update_task(id):
    task = Task.select().where(Task.id == id).get()
    if task == None: return not_found_object('task', id)
    for field in Task._meta.fields:
        if field in request.json:
            setattr(task, field, request.json[field])
    if task.save() != 1:
        return {'error': 1, 'error_message': 'can not save task#%d' % id}
    return {'error': 0}

@app.delete('/tasks/<id:int>')
def delete_task(id):
    query = Task.select().where(Task.id == id)
    if len(query) == 0: return not_found_object('task', id)
    task = query.get()
    Record.delete().where(Record.task_id == task.id).execute()
    if task.delete_instance() != 1:
        response.status = 403
        return {'error': 1, 'error_message': 'can not delete task#%d' % id}
    return {'error': 0}

app.run(host='127.0.0.1', port=1234, server='waitress')
