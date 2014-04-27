#!/usr/bin/env python2.7

import sys
import json
from qpid.messaging import *

try:
	url, priority = sys.argv[1:]
except ValueError:
	print >>sys.stderr, "Required parameters: <url> <priority> <public>"
	sys.exit(1)

msgbody = json.dumps({
	'url': url
	})


conn = Connection('localhost')
conn.open()
sess = conn.session()

topic = 'url.org' if not '--public' in sys.argv else 'url.public'

sender = sess.sender('org.blocked/' + topic)

msg = Message(msgbody)
msg.priority = int(priority)
sender.send(msg)

sess.acknowledge()
