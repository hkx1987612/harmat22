"""Exact three-file public localization follow-up; never changes native owners."""
import importlib.util
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('language_bridges_base', HERE / '2026-10-17-refresh-language-header.py')
M = importlib.util.module_from_spec(spec)
spec.loader.exec_module(M)
M.PREFIX = M.D.H.BACKUPS + '/internal-language-bridges-'
M.LOCK = M.D.H.BACKUPS + '/.internal-language-bridges.lock'
M.PREVIOUS = M.D.H.BACKUPS + '/internal-language-dictionary-57956dc7b29f4687b945b3648ea52312'
M.CHANGED = (M.D.ASSET + '/runtime.js', M.D.ASSET + '/zh-Hans.json', M.D.MAIN)
M.OLD_VERSION = b'Version: 1.0.4'
M.NEW_VERSION = b'Version: 1.1.0'
M.PRIOR = {
    M.D.ASSET + '/runtime.js': '2b1b7b78b92a062d9e53b68f1ed16b53016cb066d9ce0a08f6c9716849e3dabb',
    M.D.ASSET + '/zh-Hans.json': '725c2e77dba6d697fd6c3063eff48123174e2287508af693ce89e742a18a086f',
    M.D.MAIN: '99e4ca97271cb37411b13d6234529eac0181263eadeccd526301dca723c648f7',
}

if __name__ == '__main__':
    M.main()
