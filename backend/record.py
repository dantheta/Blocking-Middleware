
import MySQLdb
import MySQLdb.cursors
import json
from qpid.messaging import *

conn = Connection('localhost')
conn.open()
sess = conn.session()
receiver = sess.receiver('results')

db = MySQLdb.connect('localhost','root','','DB_NAME')
c = db.cursor(MySQLdb.cursors.DictCursor)

while True:
	msg = receiver.fetch()
	data = json.loads(msg.content)

	# probe
	c.execute("select * from probes where uuid = %s", [data['probe_uuid']])
	probe = c.fetchone()
	print probe

	# isp
	c.execute("select * from isps left join isp_aliases on ispid = isps.id \
		where name = %s or alias = %s", [data['network_name'], data['network_name']])
	isp = c.fetchone()
	print isp

	c.execute("select * from urls where url = %s", data['url'])
	url = c.fetchone()
	print url

	if url is None or isp is None or probe is None:
		print >>sys.stderr, "Something went wrong!"
		break

	c.execute("insert into results(urlID,probeID,config,ip_network,status,http_status,network_name, created) \
		values (%s,%s,%s,%s,%s,%s,%s,now())",
		[
			url['urlID'],probe['id'], data['config'],data['ip_network'],
			data['status'],data['http_status'], data['network_name'],
		])
	c.execute(
		"update urls set polledSuccess = polledSuccess + 1 where urlID = %s",
		[url['urlID']]
		)
	c.execute(
		"update queue set results=results+1 where urlID = %s and IspID = %s",
		[url['urlID'], isp['id']]
		)
	c.execute(
		"update probes set probeRespRecv=probeRespRecv+1,lastSeen=now() where uuid=%s",
		[probe['uuid']]
		)
	db.commit()
	sess.acknowledge()

