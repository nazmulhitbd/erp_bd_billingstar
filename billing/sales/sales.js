/* Shared helpers for the sales pages. */
function money(v){return (Number(v)||0).toFixed(2)}
function qtyFmt(v){return (Number(v)||0).toFixed(3)}
function num(id){const el=document.getElementById(id);return el?Number(el.value||0):0}
function safe(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]))}
/* Attach a filtered suggestion list to an input. */
function attachSuggest(input,box,data,fields,label,onPick){
  if(!input||!box)return;
  const show=()=>{const term=input.value.trim().toLowerCase();
    const matches=(term?data.filter(o=>fields.some(f=>String(o[f]||'').toLowerCase().includes(term))):data).slice(0,12);
    box.innerHTML='';matches.forEach(o=>{const b=document.createElement('button');b.type='button';b.textContent=label(o);
      b.addEventListener('click',()=>{onPick(o);box.hidden=true});box.appendChild(b)});box.hidden=!matches.length};
  input.addEventListener('input',show);input.addEventListener('focus',show);
  document.addEventListener('click',e=>{if(e.target!==input&&!box.contains(e.target))box.hidden=true});
}
function customerLabel(c){return `${c.customer_name} · ${c.mobile||''}`}
function productLabel(p){return `${p.product_code||''} ${p.model_caption||''} · ${p.description} (${money(p.default_unit_price)})`.trim()}
/* Fill customer fields that exist on the page. */
function fillCustomer(c){const map={customer_id:'customer_id',customer_name:'customer_name',customer_group:'customer_group',mobile:'mobile',attention:'attention',address:'address',bin:'bin',nid:'nid'};
  Object.entries(map).forEach(([id,key])=>{const el=document.getElementById(id);if(el)el.value=c[key]??''})}
