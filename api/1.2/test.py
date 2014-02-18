
import os,sys
import getopt
import requests
import datetime

import hmac, hashlib
import logging



optlist, optargs = getopt.getopt(sys.argv[1:],'v', [
	'email=',
	'host=',
	'port=',
	'password=',
	'secret=',
	'url=',
        'fuzzdate',

        # probe registration
        'probeseed=', 
        'probehmac=',
	])
opts = dict(optlist)

logging.basicConfig(
	level = logging.DEBUG if '-v' in opts else logging.INFO,
	)

class TestClient:
	MODES = ['user','user_status','submit','prepare_probe','register_probe']
	PREFIX='/api/1.2/'

	def __init__(self, options):
		self.opts = options
		self.host = options.get('--host','localhost')
		self.port = options.get('--port','80')
		self.secret = options.get('--secret','')

        def timestamp(self):
                if '--fuzzdate' in opts:
                        return datetime.datetime.now().replace(hour=1).strftime('%Y-%m-%d %H:%M:%S')
                return datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S')

	def run(self, mode):
		assert mode in self.MODES
		return getattr(self, mode)()

	def user(self):
		rq = requests.post('http://' + self.host +":"+self.port+ self.PREFIX+'register/user',
			data={'email': self.opts['--email'],'password': self.opts['--password']}
			)
		return rq.status_code, rq.content

	def user_status(self):
                ts = self.timestamp()
		rq = requests.get('http://' + self.host+":"+self.port + self.PREFIX+'status/user',
			params={
				'email': self.opts['--email'],
                                'date': ts,
				'signature': self.sign(self.opts['--email'], ts),
				}
			)
		return rq.status_code, rq.content

        def prepare_probe(self):
                ts = self.timestamp()
		rq = requests.post('http://' + self.host+":"+self.port + self.PREFIX+'prepare/probe',
			data={
				'email': self.opts['--email'],
                                'date': ts,
				'signature': self.sign(self.opts['--email'], ts),
				}
			)
		return rq.status_code, rq.content
                
        def register_probe(self):
                uuid = hashlib.md5(self.opts['--probeseed'] + '-' + self.opts['--probehmac']).hexdigest()
		rq = requests.post('http://' + self.host+":"+self.port + self.PREFIX+'register/probe',
			data={
				'email': self.opts['--email'],
                                'probe_seed': self.opts['--probeseed'],
                                'probe_uuid': uuid,
				'signature': self.sign(uuid),
				}
			)
		return rq.status_code, rq.content
                
                

	def submit(self):
		rq = requests.post('http://' + self.host+":"+self.port + self.PREFIX + 'submit/url',
		data = {
			'email': opts['--email'],
			'url': opts['--url'],
			'signature': self.sign(opts['--url']),
			})
		return rq.status_code, rq.content

	def sign(self, *args):
                msg = ':'.join([str(x) for x in args])
		hm = hmac.new(self.secret, msg, hashlib.sha512)
		return hm.hexdigest()
		

		
client = TestClient(opts)
print client.run(optargs[0])
