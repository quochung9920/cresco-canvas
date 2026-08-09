(function(){
'use strict';
var roots=document.querySelectorAll('.cresco-filterable-loop[data-cresco-query="1"]');
if(!roots.length||!window.fetch){return;}

function debounce(fn,wait){var timer;return function(){var args=arguments,ctx=this;clearTimeout(timer);timer=setTimeout(function(){fn.apply(ctx,args);},wait);};}
function parseJson(response){if(!response.ok){return response.json().catch(function(){return{};}).then(function(body){throw new Error((body&&body.message)||'Request failed');});}return response.json();}

roots.forEach(function(root){
 var endpoint=root.getAttribute('data-endpoint');
 var payload=root.getAttribute('data-payload');
 var signature=root.getAttribute('data-signature');
 var mode=root.getAttribute('data-mode')||'ajax';
 var syncUrl=root.getAttribute('data-sync-url')==='1';
 var instance=root.getAttribute('data-instance');
 var form=root.querySelector('.cresco-filterable-loop__filters');
 var results=root.querySelector('.cresco-filterable-loop__results');
 var navigation=root.querySelector('.cresco-filterable-loop__navigation');
 var status=root.querySelector('.cresco-filterable-loop__status');
 var currentPage=parseInt(root.getAttribute('data-current-page')||'1',10)||1;
 var maxPages=parseInt(root.getAttribute('data-max-pages')||'1',10)||1;
 var abortController=null;
 var requestSequence=0;
 var observer=null;

 function scopedName(suffix){return instance+'_'+suffix;}
 function filters(){
  var output={search:'',tax:{}};
  if(!form){return output;}
  var search=form.querySelector('input[type="search"]');
  if(search){output.search=search.value||'';}
  form.querySelectorAll('select[data-taxonomy]').forEach(function(select){if(select.value){output.tax[select.getAttribute('data-taxonomy')]=[select.value];}});
  return output;
 }
 function updateUrl(page,replace){
  if(!syncUrl||!window.history){return;}
  var url=new URL(window.location.href);
  var state=filters();
  var searchKey=scopedName('s');
  if(state.search){url.searchParams.set(searchKey,state.search);}else{url.searchParams.delete(searchKey);}
  if(form){form.querySelectorAll('select[data-taxonomy]').forEach(function(select){var key=scopedName(select.getAttribute('data-taxonomy'));if(select.value){url.searchParams.set(key,select.value);}else{url.searchParams.delete(key);}});}
  var pageKey=scopedName('page');
  if(page>1){url.searchParams.set(pageKey,String(page));}else{url.searchParams.delete(pageKey);}
  window.history[replace?'replaceState':'pushState']({crescoQuery:instance,page:page},'',url.toString());
 }
 function readUrl(){
  var url=new URL(window.location.href);
  if(form){
   var search=form.querySelector('input[type="search"]');
   if(search){search.value=url.searchParams.get(scopedName('s'))||'';}
   form.querySelectorAll('select[data-taxonomy]').forEach(function(select){select.value=url.searchParams.get(scopedName(select.getAttribute('data-taxonomy')))||'';});
  }
  return Math.max(1,parseInt(url.searchParams.get(scopedName('page'))||'1',10)||1);
 }
 function setLoading(loading){
  root.classList.toggle('is-loading',loading);
  root.setAttribute('aria-busy',loading?'true':'false');
  if(form){form.querySelectorAll('input,select,button').forEach(function(control){control.disabled=loading;});}
  var more=root.querySelector('.cresco-filterable-loop__more');if(more){more.disabled=loading;}
  if(status){status.textContent=loading?'Loading results…':'';}
 }
 function setError(message){if(status){status.textContent=message||'Unable to load results.';}root.classList.add('has-error');}
 function updateNavigation(data){
  currentPage=data.page||1;maxPages=data.maxPages||1;
  root.setAttribute('data-current-page',String(currentPage));root.setAttribute('data-max-pages',String(maxPages));
  if(mode==='ajax'){
   navigation.innerHTML=data.pagination||'';
  }else if(mode==='load_more'){
   var more=navigation.querySelector('.cresco-filterable-loop__more');if(more){more.hidden=!data.hasMore;}
  }else if(mode==='infinite'){
   var sentinel=navigation.querySelector('.cresco-filterable-loop__sentinel');if(sentinel){sentinel.hidden=!data.hasMore;}
  }
 }
 function request(page,append,historyMode){
  page=Math.max(1,page||1);
  requestSequence+=1;var sequence=requestSequence;
  if(abortController){abortController.abort();}
  abortController=window.AbortController?new AbortController():null;
  setLoading(true);root.classList.remove('has-error');
  return fetch(endpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},signal:abortController?abortController.signal:undefined,body:JSON.stringify({payload:payload,signature:signature,filters:filters(),page:page})})
   .then(parseJson)
   .then(function(data){
    if(sequence!==requestSequence){return;}
    if(append){results.insertAdjacentHTML('beforeend',data.html||'');}else{results.innerHTML=data.html||'<p class="cresco-filterable-loop__empty">No results found.</p>';}
    updateNavigation(data);
    if(status){status.textContent=String(data.foundPosts||0)+' results';}
    if(historyMode!=='none'){updateUrl(data.page||1,historyMode==='replace');}
    root.dispatchEvent(new CustomEvent('cresco:query-updated',{bubbles:true,detail:data}));
   })
   .catch(function(error){if(error&&error.name==='AbortError'){return;}setError(error&&error.message);})
   .finally(function(){if(sequence===requestSequence){setLoading(false);}});
 }
 var submit=debounce(function(){request(1,false,'push');},250);
 if(form){
  form.addEventListener('submit',function(event){event.preventDefault();request(1,false,'push');});
  form.addEventListener('change',function(){request(1,false,'push');});
  var search=form.querySelector('input[type="search"]');if(search){search.addEventListener('input',submit);}
 }
 root.addEventListener('click',function(event){
  var pageLink=event.target.closest('[data-cresco-page]');
  if(pageLink&&root.contains(pageLink)){event.preventDefault();request(parseInt(pageLink.getAttribute('data-cresco-page'),10)||1,false,'push');return;}
  var more=event.target.closest('.cresco-filterable-loop__more');
  if(more&&root.contains(more)){event.preventDefault();if(currentPage<maxPages){request(currentPage+1,true,'replace');}}
 });
 if(mode==='infinite'&&'IntersectionObserver' in window){
  var sentinel=root.querySelector('.cresco-filterable-loop__sentinel');
  if(sentinel){observer=new IntersectionObserver(function(entries){entries.forEach(function(entry){if(entry.isIntersecting&&!root.classList.contains('is-loading')&&currentPage<maxPages){request(currentPage+1,true,'replace');}});},{rootMargin:'300px 0px'});observer.observe(sentinel);}
 }
 window.addEventListener('popstate',function(){var page=readUrl();request(page,false,'none');});
 if(syncUrl){updateUrl(currentPage,true);}
});
})();
