"""ONE freshly authorized TEST; exclusive intent prevents automatic re-send."""
import argparse
import importlib.util
import json
from pathlib import Path
import shlex
import urllib.error
import urllib.parse
import urllib.request
import uuid

ROOT=Path(__file__).resolve().parents[2]
MAINT=Path(__file__).resolve().parent
OUT=ROOT/'outputs/2026-10-17-internal-language/approved-once'
STATE=OUT/'state.json'
PAGE='https://harmat22.hu/property/a1-1-l2/'
ENDPOINT='https://harmat22.hu/wp-json/harmat-sales-manager/v1/offer'
# The authorized production send is complete. Also prevent replay on computer B,
# where the intentionally private local state file does not exist.
TEST_SUBMISSION_COMPLETED=True

def module(name,filename):
    spec=importlib.util.spec_from_file_location(name,MAINT/filename);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);return m

def save(state):
    tmp=OUT/('state-'+uuid.uuid4().hex+'.json')
    with tmp.open('x',encoding='utf-8') as file:json.dump(state,file,ensure_ascii=True,indent=2)
    tmp.replace(STATE)

def send():
    if TEST_SUBMISSION_COMPLETED:raise RuntimeError('This release TEST is complete. Never replay it from another computer.')
    if STATE.exists():raise RuntimeError('Existing intent/result. Never automatically submit again.')
    OUT.mkdir(parents=True,exist_ok=True)
    D=module('language_deploy','2026-10-17-deploy-internal-language.py')
    probe=module('language_form','2026-10-14-probe-appointment.py')
    with urllib.request.urlopen(urllib.request.Request(PAGE,headers={'User-Agent':'HarmatChineseLanguageQA/1.0'}),timeout=30) as response:
        parser=probe.PublicFormParser();parser.feed(response.read().decode('utf-8'))
    if len(parser.nonces)!=1 or not parser.nonces[0]:raise RuntimeError('Fresh nonce not unambiguous')
    r=D.H.Remote()
    try:before=D.snapshot(r)
    finally:r.close()
    marker='HARMAT-ZH-QA-20261017-'+uuid.uuid4().hex[:8]
    params={
        'your-name':'TESZT - 中文模式预约测试',
        'your-email':'ertekesites@harmat22.hu','your-phone':'+36300733375',
        'your-date':'2026-10-20','your-time':'09:00-12:00',
        'your-message':'TESZT，请勿回访 / ne hívják vissza. 中文切换验证，不是真实客户或房源预订，不修改客户数据。 '+marker,
        'selected-apartment':'A1-1-L2','selected-url':PAGE,
        'lead_source':'Közösségi média','privacy-acceptance':'1',
        '_harmat_offer_nonce':parser.nonces[0],
        'source_url':PAGE,'landing_page':PAGE,
        'utm_source':'maintenance_test','utm_medium':'qa_no_ads',
    }
    if len(params['your-message'])>200:raise RuntimeError('Test marker could be truncated')
    state={'status':'SEND_INTENT_NO_RETRY','marker':marker,'before':before,
           'expected':{k:v for k,v in params.items() if k!='_harmat_offer_nonce'}}
    with STATE.open('x',encoding='utf-8') as file:json.dump(state,file,ensure_ascii=True,indent=2)
    request=urllib.request.Request(ENDPOINT,data=urllib.parse.urlencode(params).encode(),method='POST',headers={
        'User-Agent':'HarmatChineseLanguageQA/1.0','Origin':'https://harmat22.hu','Referer':PAGE,
        'Content-Type':'application/x-www-form-urlencoded'})
    try:
        with urllib.request.urlopen(request,timeout=45) as response:
            result=json.loads(response.read());status=response.status
    except urllib.error.HTTPError as error:
        state.update(status='REJECTED_NO_RETRY',http_status=error.code)
        save(state);raise
    state.update(status='RECEIVED_NO_RETRY',http_status=status,result=result);save(state)
    if status!=200 or result.get('success') is not True or type(result.get('id'))is not int:raise RuntimeError('Unexpected result. No retry.')
    print(json.dumps({'status':'ONE_APPROVED_TEST_RECEIVED','http_status':status,'record_id_received':True,'no_browser_ad_pixels':True}))

def verify():
    state=json.loads(STATE.read_text());result=state['result'];identifier=result['id']
    if type(identifier)is not int or identifier<=0 or result.get('success')is not True:raise RuntimeError('Unproved test ownership')
    D=module('language_verify','2026-10-17-deploy-internal-language.py');r=D.H.Remote()
    try:
        expect=state['expected']
        code='''if (!defined('WP_CLI') || !WP_CLI) throw new RuntimeException('CLI only');
$id=ID_VALUE;$expected=EXPECTED_VALUE;$marker=MARKER_VALUE;
$p=get_post($id);if(!$p||$p->post_type!=='harmat_offer_lead'||$p->post_status!=='private'||strpos($p->post_content,$marker)===false)throw new RuntimeException('Test identity mismatch');
$map=array('_harmat_offer_email'=>'your-email','_harmat_offer_phone'=>'your-phone','_harmat_offer_date'=>'your-date','_harmat_offer_time'=>'your-time','_harmat_offer_apartment'=>'selected-apartment');
foreach($map as $meta=>$key){if((string)get_post_meta($id,$meta,true)!==(string)$expected[$key])throw new RuntimeException('Test metadata mismatch');}
$posted=get_post_meta($id,'_harmat_offer_posted',true);if(!is_array($posted))throw new RuntimeException('Missing posted data');
foreach(array('your-name','your-message','lead_source')as$key){if(!isset($posted[$key])||(string)$posted[$key]!==$expected[$key])throw new RuntimeException('Test original text/source mismatch');}
$sent=get_post_meta($id,'_harmat_offer_mail_status',true)==='sent';
foreach(array('_harmat_offer_sales_mail_sent_at','_harmat_offer_customer_mail_sent_at')as$key){$sent=$sent&&(string)get_post_meta($id,$key,true)!=='';}
foreach(array('_harmat_offer_sales_mail_error','_harmat_offer_customer_mail_error')as$key){$sent=$sent&&(string)get_post_meta($id,$key,true)==='';}
echo wp_json_encode(array('owned_test'=>true,'fields_match'=>true,'wp_mail_accepted_both'=>$sent,
 'mail_status'=>(string)get_post_meta($id,'_harmat_offer_mail_status',true),
 'sales_timestamp'=>(string)get_post_meta($id,'_harmat_offer_sales_mail_sent_at',true)!=='',
 'customer_timestamp'=>(string)get_post_meta($id,'_harmat_offer_customer_mail_sent_at',true)!=='',
 'sales_error_present'=>(string)get_post_meta($id,'_harmat_offer_sales_mail_error',true)!=='',
 'customer_error_present'=>(string)get_post_meta($id,'_harmat_offer_customer_mail_error',true)!==''));'''
        # PHP gets only approved test literals; all readback is boolean/aggregate.
        encoded=lambda v:'json_decode('+json.dumps(json.dumps(v,ensure_ascii=True)) +',true)'
        code=code.replace('ID_VALUE',str(identifier)).replace('EXPECTED_VALUE',encoded(expect)).replace('MARKER_VALUE',encoded(state['marker']))
        proof=json.loads(r.run(D.H.WP+' eval '+shlex.quote(code)))
        state['last_readback']=proof;save(state);print(json.dumps({'readback':proof}))
        if not proof['owned_test']or not proof['fields_match']or not proof['wp_mail_accepted_both']:raise RuntimeError('Test/mail readback incomplete; never resend')
        current=D.snapshot(r)
        filtered=D.D.I.SNAPSHOT.replace("WHERE post_type IN ('property','harmat_offer_lead')", "WHERE ID<>"+str(identifier)+" AND post_type IN ('property','harmat_offer_lead')").replace("WHERE p.post_type IN ('property','harmat_offer_lead')", "WHERE p.ID<>"+str(identifier)+" AND p.post_type IN ('property','harmat_offer_lead')")
        current['protected']=json.loads(r.run(D.H.WP+' eval '+shlex.quote(filtered)))
        if current!=state['before']:raise RuntimeError('Non-test protected state drift. Never restore customer data.')
        proof.update(non_test_protected_state_unchanged=True,scope='ONE_APPROVED_TEST_NO_REPEAT_WP_MAIL_NOT_INBOX')
        state['verification']=proof;save(state);print(json.dumps(proof))
    finally:r.close()

if __name__=='__main__':
    p=argparse.ArgumentParser(description=__doc__);p.add_argument('--send-once',action='store_true');p.add_argument('--verify',action='store_true');args=p.parse_args()
    if args.send_once and not args.verify:send()
    elif args.verify and not args.send_once:verify()
    else:raise SystemExit('Explicit --send-once OR --verify required. No default submission.')
