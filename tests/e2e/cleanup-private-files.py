"""Remove recorded fixture uploads in an explicitly selected local PHP-FPM runtime.

Used when FPM has a private temporary directory that WP-CLI cannot see.
Only UUID-bound test paths from the private cleanup manifest are accepted.
The temporary PHP helper requires a one-use secret and is always removed.
"""

import json
import os
import pathlib
import re
import secrets
import socket
import struct

root = pathlib.Path(__file__).resolve().parents[2]
manifest = pathlib.Path('/tmp/dkbce-playwright-e2e/cleanup-files.json')
files = json.loads(manifest.read_text())
for op, filename in files.items():
    pattern = r'dkbce-(?:(?:download-)?' + re.escape(op) + r'|release-import)-[A-Za-z0-9]+\.tmp'
    if (not re.fullmatch(r'[0-9a-f-]{36}', op)
            or pathlib.Path(filename).parent != pathlib.Path('/tmp')
            or not re.fullmatch(pattern, pathlib.Path(filename).name)):
        raise ValueError('Unsupported fixture cleanup path: ' + filename)

token = secrets.token_hex(32)
script = root / ('tests/e2e/.cleanup-'+secrets.token_hex(8)+'.php')
# The one-use secret prevents HTTP callers from invoking this temporary helper.
body='<?php\nif (!hash_equals('+json.dumps(token)+', $_SERVER["HTTP_X_DKBCE_FIXTURE_CLEANUP"] ?? "")) { http_response_code(403); exit; }\n'
body+='$files = json_decode('+json.dumps(json.dumps(list(files.values())))+', true); $removed=0; $missing=0; $failed=array();\n'
body+='foreach($files as $file) { if (!file_exists($file)) { ++$missing; continue; } if (unlink($file)) { ++$removed; } else { $failed[]=basename($file); } }\n'
body+='header("Content-Type: application/json"); echo json_encode(array("removed"=>$removed,"already_absent"=>$missing,"failed"=>$failed));\n'

def record(record_type, data):
    pad = (-len(data)) % 8
    return struct.pack('!BBHHBB', 1, record_type, 1, len(data), pad, 0) + data + b'\0' * pad

def pair(key, value):
    key, value = key.encode(), value.encode()

    def size(length):
        return bytes([length]) if length < 128 else struct.pack('!I', length | 0x80000000)

    return size(len(key)) + size(len(value)) + key + value
try:
    script.write_text(body)
    script.chmod(0o644)
    params={'GATEWAY_INTERFACE':'CGI/1.1','SCRIPT_FILENAME':str(script),'SCRIPT_NAME':'/fixture-cleanup.php','REQUEST_METHOD':'POST','SERVER_PROTOCOL':'HTTP/1.1','SERVER_NAME':'localhost','SERVER_PORT':'80','REMOTE_ADDR':'127.0.0.1','CONTENT_LENGTH':'0','HTTP_X_DKBCE_FIXTURE_CLEANUP':token}
    with socket.socket(socket.AF_UNIX) as sock:
        sock.settimeout(30)
        sock.connect(os.environ['DKBCE_E2E_FPM_SOCKET'])
        sock.sendall(record(1,struct.pack('!HB5x',1,0))+record(4,b''.join(pair(k,v)for k,v in params.items()))+record(4,b'')+record(5,b''))
        def readn(n):
            data = b''
            while len(data) < n:
                chunk = sock.recv(n - len(data))
                if not chunk:
                    raise RuntimeError('Incomplete FastCGI response')
                data += chunk
            return data
        out, err = b'', b''
        while True:
            version, record_type, request_id, length, pad, _ = struct.unpack('!BBHHBB', readn(8))
            data = readn(length)
            readn(pad)
            if record_type == 6:
                out += data
            if record_type == 7:
                err += data
            if record_type == 3:
                break
        response = json.loads(out.split(b'\r\n\r\n',1)[1])
        print(json.dumps(response))
        if response['failed'] or err:
            raise RuntimeError('Private fixture file cleanup failed: ' + err.decode())
finally:
    script.unlink(missing_ok=True)
