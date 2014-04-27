
import MySQLdb
import json
from qpid.messaging import *

conn = Connection('localhost')
conn.open()
sess = conn.session()
sender = sess.sender('org.blocked/url.org')

db = MySQLdb.connect('localhost','root','','DB_NAME')
c = db.cursor()
c.execute("select url from urls where source = 'alexa'")
for row in c:
	msgbody = json.dumps({'url': row[0]})
	msg = Message(msgbody)
	msg.priority = 3
	sender.send(msg)

sess.acknowledge()

