"""Real multi-process financial retries against empty spacekind_test MySQL only.
Never points at the development/deployed DB; PHP enforces the database/environment.
"""
import concurrent.futures
import io
import json
import os
from pathlib import Path
import subprocess
from pypdf import PdfReader

ROOT=Path(__file__).resolve().parent.parent
ENV={**os.environ,'APP_ENV':'testing','DB_DATABASE':'spacekind_test','SPACEKIND_DEMO':'false'}
def run(mode,*args):
    p=subprocess.run(['php','qa/finance_concurrency.php',mode,*map(str,args)],cwd=ROOT,env=ENV,text=True,capture_output=True,timeout=45)
    if p.returncode:raise RuntimeError(p.stdout+p.stderr)
    return p.stdout.strip()
def parallel(mode,indices):
    with concurrent.futures.ThreadPoolExecutor(max_workers=6) as pool:return list(pool.map(lambda i:run(mode,i),indices))

def main():
    prepared=False
    try:
        run('prepare');prepared=True
        numbers=parallel('issue',range(6))
        receipts=parallel('pay',range(6))
        run('advance');allocations=parallel('allocate',range(4))
        result=json.loads(run('verify'))
        checks={'six_simultaneous_issues_are_unique':len(set(numbers))==6,'simultaneous_retry_returns_same_payment':len(set(receipts))==1,'six_numbers_allocated_without_gaps':result['invoice_sequence']==6 and all(n.endswith('/'+str(i).zfill(5)) for i,n in enumerate(result['invoice_numbers'],1)),'one_receipt_per_recorded_payment':result['receipt_count']==result['payment_count']==2,'one_allocation_per_retry_key':result['allocation_count']==2,'derived_invoice_balance':result['paid_minor']==15000 and result['balance_minor']==103000,'advance_balance':result['available_advance_minor']==5000,'all_allocation_retries_succeed':allocations==['allocated']*4}
        pdf=PdfReader(str(ROOT/'qa/phase2-multipage.pdf'));text='\n'.join(page.extract_text() for page in pdf.pages)
        checks.update({'multipage_pdf':len(pdf.pages)>=3,'all_fifty_lines_in_pdf':all(f'Synthetic scope line {i:02}' in text for i in range(1,51)),'pdf_terms_notes_and_tax_preserved':'FINAL SENTINEL' in text and 'CGST' in text and 'SGST' in text and 'Synthetic term' in text,'draft_pdf_clearly_marked':'DRAFT / NOT ISSUED' in text})
        report={'complete':True,'database':'spacekind_test','parallel_issue_workers':6,'parallel_payment_retries':6,'parallel_allocation_retries':4,'checks':checks,'pdf_pages':len(pdf.pages),'result':result}
        (ROOT/'qa/phase2-concurrency-results.json').write_text(json.dumps(report,indent=2));print(json.dumps(report,indent=2));assert all(checks.values()),'Concurrency/PDF assertion failed'
    finally:
        if prepared:print(run('cleanup'))

if __name__=='__main__':main()
