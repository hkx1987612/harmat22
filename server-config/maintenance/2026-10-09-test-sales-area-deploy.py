"""Test the orchestrator's safety comparison without SSH or WordPress."""
import ast
import copy
import json
import re
from pathlib import Path

tree = ast.parse(Path(__file__).with_name('2026-10-09-deploy-sales-area.py').read_text(encoding='utf-8'))
definition = next(node for node in tree.body if isinstance(node, ast.FunctionDef) and node.name == 'compare')
assignment = next(node for node in tree.body if isinstance(node, ast.Assign) and any(isinstance(t, ast.Name) and t.id == 'EXPECTED' for t in node.targets))
events = []
scope = {'json':json,'re':re,'EXPECTED':ast.literal_eval(assignment.value),'note':lambda *args:events.append(args)}
exec(compile(ast.Module(body=[definition], type_ignores=[]), '<isolated-safety-compare>', 'exec'), scope)
before = {'facts':{},'propertyCount':124,'sources':{'production':'unchanged'},'errorLog':None,
          'savedQuoteRows':68,'savedQuoteHash':'unchanged','offerLeads':68,
          'options':{'mail':{'sha256':'same','autoload':'off'},'indexnow':{'sha256':'same','autoload':'off'},
                     'cron':{'sha256':'old','autoload':'on'}},
          'cronEvents':[{'hook':'action_scheduler_run_queue','timestamp':1000,'schedule':'every_minute','interval':60,'argsSHA256':'same'},
                        {'hook':'harmat_legal_daily_task_reminder','timestamp':2000,'schedule':'daily','interval':86400,'argsSHA256':'same'}]}
for post_id, (old, _) in scope['EXPECTED'].items():
    before['facts'][post_id] = {'_harmat_sales_area':old,'property_price':'unchanged'}
for post_id in ['4419','5212','5332','5490']:
    before['facts'][post_id] = {'_harmat_sales_area':'47.83'}
after = copy.deepcopy(before)
for post_id, (_, new) in scope['EXPECTED'].items():
    after['facts'][post_id]['_harmat_sales_area'] = new
count = 0


def check(candidate, should_pass, reverse=False, original=before, housekeeping=False):
    global count
    try:
        scope['compare'](original, candidate, reverse, allow_housekeeping=housekeeping)
    except RuntimeError:
        assert not should_pass
    else:
        assert should_pass
    count += 1


check(after, True)
minute = copy.deepcopy(after)
minute['cronEvents'][0]['timestamp'] += 120
minute['options']['cron']['sha256'] = 'new'
check(minute, True)
for key, value in [('timestamp',999),('timestamp',8201),('schedule','daily'),('interval',120),('argsSHA256','different')]:
    candidate = copy.deepcopy(after)
    candidate['cronEvents'][0][key] = value
    check(candidate, False)
candidate = copy.deepcopy(after)
candidate['cronEvents'][1]['timestamp'] += 1
check(candidate, False)
candidate = copy.deepcopy(after)
candidate['cronEvents'].append({'hook':'harmat_sai_send_indexnow_queue','timestamp':1050,'schedule':False,'interval':None,'argsSHA256':'same'})
check(candidate, False)
for key in ['mail','indexnow']:
    candidate = copy.deepcopy(after)
    candidate['options'][key]['sha256'] = 'different'
    check(candidate, False)
candidate = copy.deepcopy(after)
candidate['options']['cron']['autoload'] = 'off'
check(candidate, False)
for key in ['propertyCount','sources','errorLog','savedQuoteRows','savedQuoteHash']:
    candidate = copy.deepcopy(after)
    candidate[key] = 'changed'
    check(candidate, False)
candidate = copy.deepcopy(after)
candidate['facts']['4349']['property_price'] = 'changed'
check(candidate, False)
candidate = copy.deepcopy(after)
candidate['facts']['4419']['_harmat_sales_area'] = '48.9'
check(candidate, False)
candidate = copy.deepcopy(after)
candidate['offerLeads'] -= 1
check(candidate, False)
candidate = copy.deepcopy(after)
candidate['offerLeads'] += 1
check(candidate, True)
check(before, True, reverse=True, original=after)
background_before=copy.deepcopy(before)
background_after=copy.deepcopy(after)
job={'hook':'aios_15_minutes_cron_event','timestamp':1000,'schedule':'aios-every-15-minutes','interval':900,'argsSHA256':'same'}
background_before['cronEvents'].append(job)
background_after['cronEvents'].append({**job,'timestamp':1900})
check(background_after,False,original=background_before)
check(background_after,True,original=background_before,housekeeping=True)
for key,value in [('timestamp',1901),('argsSHA256','changed'),('schedule','daily')]:
    candidate=copy.deepcopy(background_after)
    candidate['cronEvents'][-1][key]=value
    check(candidate,False,original=background_before,housekeeping=True)
for hook in ['harmat_bw_hourly_check','email_delivery','indexnow_background']:
    original=copy.deepcopy(background_before)
    candidate=copy.deepcopy(background_after)
    original['cronEvents'][-1]['hook']=hook
    candidate['cronEvents'][-1]['hook']=hook
    check(candidate,False,original=original,housekeeping=True)
gc_before=copy.deepcopy(before)
gc_after=copy.deepcopy(after)
gc={'hook':'wp_cache_gc','timestamp':1000,'schedule':False,'interval':None,'argsSHA256':'same'}
gc_before['cronEvents'].append(gc)
gc_after['cronEvents'].append({**gc,'timestamp':1800})
check(gc_after,False,original=gc_before)
check(gc_after,True,original=gc_before,housekeeping=True)
bad_gc=copy.deepcopy(gc_after)
bad_gc['cronEvents'][-1]['argsSHA256']='changed'
check(bad_gc,False,original=gc_before,housekeeping=True)
other_gc_before=copy.deepcopy(gc_before)
other_gc_after=copy.deepcopy(gc_after)
other_gc_before['cronEvents'][-1]['hook']='unknown_one_off'
other_gc_after['cronEvents'][-1]['hook']='unknown_one_off'
check(other_gc_after,False,original=other_gc_before,housekeeping=True)
print('PASS: ' + str(count) + ' isolated deploy-safety cases; no network, customer data or writes.')
