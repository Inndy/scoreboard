from peewee import *

import config
import json

__all__ = ('Task', 'Record')

db = MySQLDatabase(**config.db)

class ModelRepr:
    def __repr__(self):
        return '%s(%s)' % (self.__class__.__name__, ', '.join( '%s=%r' % (name, getattr(self, name)) for name in sorted(self._meta.fields) ))

class Dictfy:
    def as_dict(self):
        return { key: getattr(self, key) for key in self._meta.fields }

    def json(self):
        return json.dumpss(self.as_dict())

class Task(Model, ModelRepr, Dictfy):
    class Meta:
        database = db
        db_table = 'tasks'
        order_by = ('ordering', 'id')

    ordering = IntegerField()
    type = CharField()
    name = CharField()
    link = CharField()
    flag = CharField()
    points = IntegerField()
    text = TextField()

    fields = ('ordering', 'type', 'name', 'link', 'flag', 'points', 'text')

    def row(self):
        return tuple(getattr(self, name) for name in self.fields)

class Record(Model, ModelRepr, Dictfy):
    class Meta:
        database = db
        db_table = 'records'

    task = ForeignKeyField(Task, related_name='records')
    name = CharField()
    time = DateTimeField()
    ip = CharField()

db.connect()
