"""Dictionary-only follow-up; exact v1.0.3 preimage, no runtime or form changes."""
import importlib.util
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('language_dictionary_base', HERE / '2026-10-17-refresh-language-header.py')
M = importlib.util.module_from_spec(spec)
spec.loader.exec_module(M)
M.PREFIX = M.D.H.BACKUPS + '/internal-language-dictionary-'
M.LOCK = M.D.H.BACKUPS + '/.internal-language-dictionary.lock'
M.PREVIOUS = M.D.H.BACKUPS + '/internal-language-dictionary-d60a3cce659543bca00cfce526e788a3'
M.CHANGED = (M.D.ASSET + '/zh-Hans.json', M.D.MAIN)
M.OLD_VERSION = b'Version: 1.0.3'
M.NEW_VERSION = b'Version: 1.0.4'
M.PRIOR = {
    M.D.ASSET + '/zh-Hans.json': '3e561711d88c95e5f841d1fef5faebf62c3fe19dcdf93bc293010b894bd7a845',
    M.D.MAIN: 'a31c2458835c7315dbf46e7c12eb9ca68cf56bf78c217efbf8c802f5462f9b59',
}

if __name__ == '__main__':
    M.main()
