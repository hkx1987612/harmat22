"""User follow-up: always-visible control, exact three-file v1 header preimage."""
import importlib.util
from pathlib import Path

HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('language_header_visible_base',HERE/'2026-10-17-refresh-language-header.py')
M=importlib.util.module_from_spec(spec);spec.loader.exec_module(M)
M.PREVIOUS=M.D.H.BACKUPS+'/internal-language-header-299da2467f6b45009dea44939f2968de'
M.OLD_VERSION=b'Version: 1.0.1'
M.NEW_VERSION=b'Version: 1.0.2'
M.PRIOR={
    M.D.ASSET+'/runtime.css':'9252bc35c5579266d98e531e01e4495f27a81a3032f787290ddb1c054c1dd305',
    M.D.ASSET+'/bootstrap.js':'4e8424da54e9cc7bcfb169727b4abb45c9934b8c88bb48736703fd123b1545ea',
    M.D.MAIN:'37bb6e89f0f688db8e4fe892f9d9708a1e00b7bf7c2a8125aa0d6a6758638fc9',
}

if __name__=='__main__':M.main()
