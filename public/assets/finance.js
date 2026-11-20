'use strict';
(() => {
 const form=document.querySelector('[data-finance-editor]');
 if(form){
  const lines=form.querySelector('[data-finance-lines]');let next=Math.max(0,...[...lines.querySelectorAll('[name]')].map(el=>Number((el.name.match(/^items\[(\d+)\]/)||[])[1]||0)))+1;
  let catalogue=[];try{catalogue=JSON.parse(form.dataset.catalogue)}catch{}
  const preview=row=>{const val=n=>Number(row.querySelector(`[data-line-field="${n}"]`)?.value||0);const amount=val('quantity')*val('rate')-val('discount');row.querySelector('[data-line-preview]').textContent=Number.isFinite(amount)?'INR '+Math.max(0,amount).toFixed(2):'Check values'};
  const add=item=>{if(lines.children.length>=50)return;const fragment=document.createElement('template');fragment.innerHTML=document.getElementById('financeLineTemplate').innerHTML.replaceAll('__INDEX__',String(next++));const row=fragment.content.firstElementChild;if(item)for(const [key,value] of Object.entries(item)){const input=row.querySelector(`[data-line-field="${key}"]`);if(input)input.value=value??'';}lines.append(row);preview(row);row.querySelector('input')?.focus();};
  form.querySelector('[data-add-line]')?.addEventListener('click',()=>add());
  form.querySelector('[data-add-catalogue]')?.addEventListener('click',()=>{const id=Number(form.querySelector('[data-catalogue-choice]').value);const item=catalogue.find(i=>i.id===id);if(item)add(item)});
  lines.addEventListener('click',event=>{const button=event.target.closest('[data-remove-line]');if(button&&lines.children.length>1)button.closest('[data-finance-line]').remove()});
  lines.addEventListener('input',event=>{const row=event.target.closest('[data-finance-line]');if(row)preview(row)});lines.querySelectorAll('[data-finance-line]').forEach(preview);
 }
 document.querySelectorAll('[data-device-share]').forEach(button=>button.addEventListener('click',async()=>{try{if(navigator.share)await navigator.share({title:'Private studio document',text:'Please review this private document. The link expires and can be revoked.',url:button.dataset.url});else if(navigator.clipboard)await navigator.clipboard.writeText(button.dataset.url);}catch(error){if(error.name!=='AbortError')console.info('Use Copy private link or download the PDF instead.')}}));
})();
