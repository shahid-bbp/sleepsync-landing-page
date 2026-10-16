"""Create upload ZIPs from an explicit safe file list. Never package local secrets."""
from pathlib import Path
import hashlib
import zipfile

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'dist'
OUT.mkdir(exist_ok=True)
api = ROOT / 'backend-php/public_html/api'
frontend = [ROOT / name for name in ['index.html', 'styles.css', 'script.js', 'favicon.ico', 'favicon.png', '_config.yml']]
frontend += sorted(p for p in (ROOT / 'assets').iterdir() if p.is_file() and p.suffix in ['.png', '.webp', '.svg', '.ico'])
api_files = ['.htaccess', '.user.ini', 'submit-enquiry.php', 'test-smtp.php', 'composer.json',
             'lib/.htaccess', 'lib/security.php', 'lib/validation.php', 'lib/email-template.php',
             'PHPMailer/.htaccess', 'PHPMailer/Exception.php', 'PHPMailer/PHPMailer.php', 'PHPMailer/SMTP.php',
             'PHPMailer/LICENSE', 'PHPMailer/SHA256SUMS', 'PHPMailer/VERSION.txt']
backend = [(api / name, 'public_html/api/' + name) for name in api_files]
backend += [(ROOT / 'backend-php/config/sleepsync-config.example.php', 'config/sleepsync-config.example.php')]
backend += [(ROOT / 'docs' / name, 'docs/' + name) for name in ['HOSTINGER-PHP-DEPLOYMENT.md', 'TESTING.md']]
frontend_in_public = [(p, 'public_html/' + p.relative_to(ROOT).as_posix()) for p in frontend]
complete_site = frontend_in_public + backend
api_for_public_html = [(api / name, 'api/' + name) for name in api_files]

for filename, entries in [('sleepsync-frontend.zip', [(p, p.relative_to(ROOT).as_posix()) for p in frontend]),
                          ('sleepsync-hostinger-api.zip', backend),
                          ('sleepsync-complete-hostinger-website.zip', complete_site),
                          ('api.zip', api_for_public_html)]:

    for source, name in entries:
        assert source.is_file(), source
        assert source.resolve().is_relative_to(ROOT), source
        assert 'sleepsync-config.php' not in name and not name.startswith(('.tools/', '.test-output/'))
    with zipfile.ZipFile(OUT / filename, 'w', compression=zipfile.ZIP_DEFLATED) as archive:
        for source, name in entries:
            archive.write(source, name)
    print(filename, '-', len(entries), 'files; SHA256', hashlib.sha256((OUT / filename).read_bytes()).hexdigest())

