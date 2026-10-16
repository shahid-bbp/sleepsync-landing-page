"""Run against real PHP + PHPMailer with a local TLS SMTP fixture; sends no live mail.
Usage: python tests/backend-integration.py [path/to/php]
"""
from pathlib import Path
from concurrent.futures import ThreadPoolExecutor
from email import policy
from email.parser import BytesParser
import base64, copy, http.client, json, os, shutil, socket, socketserver, ssl, subprocess, sys, threading, time, uuid

ROOT = Path(__file__).resolve().parents[1]
PHP = sys.argv[1] if len(sys.argv) > 1 else shutil.which('php')
if not PHP:
    raise SystemExit('Pass a PHP 8.2+ executable with OpenSSL enabled.')
RUN = ROOT / '.test-output' / ('backend-' + uuid.uuid4().hex)
RUN.mkdir(parents=True)
MODE = RUN / 'mode.txt'
MODE.write_text('success')
STORAGE = RUN / 'data'
STORAGE.mkdir()
CONFIG = RUN / 'config.php'
CONFIG.write_text('<?php return [];')
env = dict(os.environ, SLEEPSYNC_CONFIG_PATH=str(CONFIG), STORAGE_PATH=str(STORAGE),
           SMTP_PASSWORD='local-test-password-only', ALLOWED_ORIGINS='https://shahid-bbp.github.io,https://thesleepsync.in',
           TEST_MODE=str(MODE), TEST_CERT=str(RUN / 'cert.pem'))
php_cmd = [PHP]
ext = Path(PHP).resolve().parent / 'ext'
if (ext / 'php_openssl.dll').is_file():
    php_cmd += ['-d', 'extension_dir=' + str(ext), '-d', 'extension=openssl']
    env['OPENSSL_CONF'] = str(Path(PHP).resolve().parent / 'extras/ssl/openssl.cnf')
subprocess.run(php_cmd + [str(ROOT / 'tests/create-certificate.php'), str(RUN)], env=env, check=True, capture_output=True)
ctx = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
ctx.load_cert_chain(RUN / 'cert.pem', RUN / 'key.pem')
messages = []

class SMTP(socketserver.StreamRequestHandler):
    def handle(self):
        try:
            self.connection.settimeout(5)
            mode = MODE.read_text().strip()
            self.wfile.write(b'220 localhost test SMTP\r\n')
            login = 0
            while True:
                line = self.rfile.readline()
                if not line: return
                upper = line.upper()
                if login:
                    if login == 1:
                        login = 2
                        self.wfile.write(b'334 UGFzc3dvcmQ6\r\n')
                    else:
                        login = 0
                        self.wfile.write(b'535 Authentication failed\r\n' if mode == 'auth-failure' else b'235 Authenticated\r\n')
                elif upper.startswith(b'EHLO'):
                    self.wfile.write(b'250-localhost\r\n250 AUTH LOGIN\r\n')
                elif upper.startswith(b'AUTH LOGIN'):
                    login = 1
                    self.wfile.write(b'334 VXNlcm5hbWU6\r\n')
                elif upper.startswith(b'DATA'):
                    self.wfile.write(b'354 End with dot\r\n')
                    data = b''
                    while True:
                        part = self.rfile.readline()
                        if part == b'.\r\n': break
                        if not part: return
                        data += part[1:] if part.startswith(b'..') else part
                    if mode == 'delivery-failure':
                        self.wfile.write(b'550 Message rejected\r\n')
                    else:
                        messages.append(data)
                        self.wfile.write(b'250 Message accepted\r\n')
                elif upper.startswith(b'QUIT'):
                    self.wfile.write(b'221 Bye\r\n')
                    return
                else:
                    self.wfile.write(b'250 OK\r\n')
        except (OSError, ssl.SSLError):
            pass

class Server(socketserver.ThreadingTCPServer):
    daemon_threads = True
    def get_request(self):
        sock, addr = super().get_request()
        return ctx.wrap_socket(sock, server_side=True), addr

smtp = Server(('127.0.0.1', 0), SMTP)
threading.Thread(target=smtp.serve_forever, daemon=True).start()
env['TEST_SMTP_PORT'] = str(smtp.server_address[1])
with socket.socket() as probe:
    probe.bind(('127.0.0.1', 0))
    port = probe.getsockname()[1]
log = (RUN / 'php.log').open('w')
process = subprocess.Popen(php_cmd + ['-S', f'127.0.0.1:{port}', '-t', str(ROOT / 'backend-php/public_html'), str(ROOT / 'tests/router.php')], env=env, stdout=log, stderr=log)
VALID = json.loads((ROOT / 'tests/valid-enquiry.json').read_text(encoding='utf-8'))
passed = 0

def reset():
    # Only delete this test run's known state file; no recursive filesystem changes.
    (STORAGE / 'submission-state.json').unlink(missing_ok=True)
    MODE.write_text('success')

def request(body=None, method='POST', headers=None, chunked=False):
    h = {'Origin': 'https://shahid-bbp.github.io', 'Content-Type': 'application/json'}
    h.update(headers or {})
    h = {k: v for k, v in h.items() if v is not None}
    raw = json.dumps(VALID if body is None else body, ensure_ascii=False).encode() if not isinstance(body, bytes) else body
    con = http.client.HTTPConnection('127.0.0.1', port, timeout=8)
    con.request(method, '/api/submit-enquiry.php', [raw] if chunked else raw, h, encode_chunked=chunked)
    res = con.getresponse()
    payload = res.read()
    result = (res.status, dict(res.getheaders()), json.loads(payload) if payload else None)
    con.close()
    return result

def expect(name, status, *args, **kwargs):
    global passed
    result = request(*args, **kwargs)
    assert result[0] == status, (name, result)
    assert status == 204 or result[2]['ok'] == (status == 200), (name, result)
    passed += 1
    return result

try:
    for _ in range(100):
        try:
            with socket.create_connection(('127.0.0.1', port), timeout=.1): break
        except OSError: time.sleep(.05)
    else: raise RuntimeError('PHP test server failed to start')
    status, headers, result = expect('preflight', 204, method='OPTIONS', headers={'Access-Control-Request-Method': 'POST', 'Access-Control-Request-Headers': 'content-type'})
    assert headers['Access-Control-Allow-Origin'] == 'https://shahid-bbp.github.io'
    expect('unknown origin', 403, headers={'Origin': 'https://evil.example'})
    expect('missing origin', 403, headers={'Origin': None})
    expect('null origin', 403, headers={'Origin': 'null'})
    expect('origin prefix spoof', 403, headers={'Origin': 'https://shahid-bbp.github.io.evil.example'})
    expect('origin with repo path', 403, headers={'Origin': 'https://shahid-bbp.github.io/sleepsync-landing-page'})
    expect('HTTP forbidden', 403, headers={'X-Test-HTTP': '1'})
    expect('GET forbidden', 405, method='GET')
    expect('preflight method forbidden', 405, method='OPTIONS', headers={'Access-Control-Request-Method': 'DELETE'})
    expect('preflight headers forbidden', 403, method='OPTIONS', headers={'Access-Control-Request-Method': 'POST', 'Access-Control-Request-Headers': 'authorization'})
    cases = [
        ('missing required', {'fullName': ''}), ('bad email', {'email': 'invalid'}),
        ('header injection', {'fullName': 'Name\r\nBcc: x@example.com'}),
        ('array instead of text', {'fullName': ['test']}), ('object instead of text', {'fullName': {'x': 'y'}}),
        ('boolean text', {'town': True}), ('numeric decimal', {'staffCount': '2.5'}),
        ('numeric exponent', {'yearsInBusiness': '1e1'}), ('numeric overflow', {'showroomArea': '10000001'}),
        ('negative number', {'proposedArea': '-1'}), ('invalid enum', {'sourceOfFunds': 'Unknown'}),
        ('invalid categories', {'currentBusiness': ['Furniture', 'Unknown']}),
        ('nested categories', {'currentBusiness': [['Furniture']]}), ('no category', {'currentBusiness': []}),
        ('string category', {'currentBusiness': 'Furniture'}), ('bad GSTIN', {'gstin': '123'}),
        ('no consent', {'consent': False}), ('string consent', {'consent': 'true'}),
        ('long comments', {'comments': 'x' * 3001}), ('no phone digits', {'mobile': '--------'}),
        ('honeypot', {'website': 'spam'}), ('honeypot type', {'website': []}),
        ('unknown field', {'injected': 'value'}),
    ]
    for name, changes in cases:
        reset()
        expect(name, 422, body=dict(VALID, **changes))
    for field in VALID:
        if field not in ['gstin', 'mattressBrands', 'spaceAddress', 'leaseYears', 'comments']:
            reset()
            data = copy.deepcopy(VALID)
            del data[field]
            expect('required ' + field, 422, body=data)
    for body in [b'{', b'[]', b'null', b'"string"', b'{"name":"\xff"}']:
        reset()
        expect('malformed JSON', 400, body=body)
    reset()
    expect('wrong content type', 415, headers={'Content-Type': 'text/plain'})
    expect('compressed request', 415, headers={'Content-Encoding': 'gzip'})
    expect('oversized body', 413, body=b'x' * 32769)
    expect('oversized chunked body', 413, body=b'x' * 32769, chunked=True)
    reset()
    before = len(messages)
    result = expect('real PHPMailer with TLS SMTP fixture', 200)
    assert result[2]['code'] == 'SMTP_ACCEPTED'
    ref = result[2]['reference']
    assert len(messages) == before + 1
    mail = BytesParser(policy=policy.default).parsebytes(messages[-1])
    assert str(mail['Subject']) == 'SleepSync franchise enquiry'
    assert str(mail['To']) == 'web@thesleepsync.in'
    assert 'web@thesleepsync.in' in str(mail['From'])
    assert 'applicant@example.com' in str(mail['Reply-To'])
    plain, html = mail.get_body(preferencelist=('plain',)).get_content().replace('\r\n', '\n'), mail.get_body(preferencelist=('html',)).get_content()
    assert '<script>' not in html and '&lt;script&gt;' in html and ref in html and ref in plain
    for field, value in VALID.items():
        if field in ['website', 'consent'] or value == '': continue
        for item in value if isinstance(value, list) else [value]:
            assert item in plain, ('missing mail field', field)
    duplicate = expect('duplicate acknowledged without another email', 200)
    assert duplicate[2]['reference'] == ref and len(messages) == before + 1
    for mode in ['auth-failure', 'delivery-failure', 'timeout']:
        reset()
        MODE.write_text(mode)
        before = len(messages)
        expect(mode, 502)
        expect('ambiguous retry blocked', 409)
        assert len(messages) == before
    reset()
    for _ in range(5): expect('count invalid attempts', 400, body=b'{')
    result = expect('IP rate limit', 429, body=b'{')
    assert int(result[1]['Retry-After']) > 0
    expect('forwarded IP cannot bypass rate limit', 429, headers={'X-Forwarded-For': '203.0.113.10'})
    reset()
    CONFIG.write_text("<?php return ['global_rate_limit_max' => 1];")
    expect('global attempt count', 400, body=b'{')
    expect('global attempt limit', 429)
    CONFIG.write_text("<?php return ['daily_mail_limit' => 1];")
    reset()
    expect('daily mail attempt count', 200)
    expect('daily mail limit', 429, body=dict(VALID, fullName='A different applicant'))
    CONFIG.write_text('<?php return [];')
    reset()
    (STORAGE / 'submission-state.json').mkdir()
    expect('unwritable state target fails closed', 503)
    (STORAGE / 'submission-state.json').rmdir()
    reset()
    (STORAGE / 'submission-state.json').write_text('{broken')
    expect('corrupt storage fails closed', 503)
    reset()
    CONFIG.write_text("<?php return ['rate_limit_max' => 0];")
    expect('invalid configuration', 503)
    CONFIG.write_text('<?php return [];')
    reset()
    with ThreadPoolExecutor(max_workers=8) as pool:
        results = list(pool.map(lambda _: subprocess.run(php_cmd + [str(ROOT / 'tests/claim-race.php')], env=env, capture_output=True, text=True, check=True).stdout, range(8)))
    assert results.count('claimed') == 1 and all(r in ['claimed', '409', '503'] for r in results), results
    passed += 1
    reset()
    expect('future custom-domain origin', 200, headers={'Origin': 'https://thesleepsync.in'})
    state = (STORAGE / 'submission-state.json').read_text()
    assert 'applicant@example.com' not in state and '98765' not in state and 'local-test-password-only' not in state
    print(f'PASS: {passed} API checks; real PHPMailer/TLS fixture, full email, CORS, validation, limits and failure paths.')
    print('No live Hostinger mail was sent. Inbox delivery remains a deployment acceptance test.')
finally:
    process.terminate()
    process.wait(timeout=5)
    smtp.shutdown()
    smtp.server_close()
    log.close()
