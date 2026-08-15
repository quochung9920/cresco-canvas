(function(window,document){
'use strict';

function all(selector,scope){return Array.prototype.slice.call((scope||document).querySelectorAll(selector));}
function decode(node){try{var raw=node.getAttribute('data-cresco-pro-config')||'',pad='='.repeat((4-raw.length%4)%4),json=decodeURIComponent(Array.prototype.map.call(atob((raw+pad).replace(/-/g,'+').replace(/_/g,'/')),function(c){return'%'+('00'+c.charCodeAt(0).toString(16)).slice(-2);}).join(''));return JSON.parse(json)||{};}catch(error){return {};}}
function number(value,fallback){var n=Number(value);return Number.isFinite(n)?n:fallback;}
function text(value,fallback){return value===undefined||value===null?fallback:String(value);}
function button(label,className){var b=document.createElement('button');b.type='button';b.className=className;b.setAttribute('aria-label',label);b.textContent=label;return b;}
function directElements(node){return Array.prototype.filter.call(node.children,function(child){return child.nodeType===1;});}
function setVars(node,config){node.style.setProperty('--cresco-pro-gap',text(config.gap,'24px'));node.dataset.perView=String(Math.max(1,number(config.slidesPerView,3)));node.dataset.perViewTablet=String(Math.max(1,number(config.tabletSlidesPerView,2)));node.dataset.perViewMobile=String(Math.max(1,number(config.mobileSlidesPerView,1)));node.style.setProperty('--cresco-pro-speed',Math.max(100,number(config.speed,550))+'ms');}

function initCarousel(node,config){
 if(node.dataset.crescoProReady)return;node.dataset.crescoProReady='1';setVars(node,config);node.classList.add('cresco-pro-carousel');
 var type=node.getAttribute('data-cresco-pro-widget')||'carousel',source=directElements(node),track;
 if(type==='slides'&&config.height)node.style.height=text(config.height,'620px');
 if(config.centered)node.classList.add('is-centered');
 if(config.adaptiveHeight)node.classList.add('is-adaptive-height');
 if(type==='loop-carousel'){track=node;source=all(':scope > .cresco-loop-card',node);node.classList.add('cresco-pro-carousel--loop');}
 else if(type==='image-carousel'){source=all(':scope > .cresco-gallery__item',node);track=node;node.classList.add('cresco-pro-carousel--gallery');}
 else{track=document.createElement('div');track.className='cresco-pro-carousel__track';source.forEach(function(item){track.appendChild(item);});node.appendChild(track);}
 if(track===node)node.classList.add('cresco-pro-carousel__track-root');
 source.forEach(function(item){item.classList.add('cresco-pro-carousel__item');});
 if(!source.length)return;
 var index=0,fade=type==='slides'&&config.transition==='fade';
 if(fade){node.classList.add('is-fade');source.forEach(function(item,i){item.classList.toggle('is-active',i===0);});}
 function perView(){if(window.matchMedia('(max-width:767px)').matches)return Math.max(1,number(config.mobileSlidesPerView,1));if(window.matchMedia('(max-width:1024px)').matches)return Math.max(1,number(config.tabletSlidesPerView,2));return Math.max(1,number(config.slidesPerView,type==='slides'?1:3));}
 function maxIndex(){return Math.max(0,source.length-perView());}
 function updatePagination(){all('.cresco-pro-carousel__dot',node).forEach(function(dot,i){dot.classList.toggle('is-active',i===index);dot.setAttribute('aria-current',i===index?'true':'false');});var fraction=node.querySelector('.cresco-pro-carousel__fraction');if(fraction)fraction.textContent=(index+1)+' / '+source.length;}
 function go(next,instant){var max=maxIndex();if(config.loop!==false){if(next<0)next=max;if(next>max)next=0;}else next=Math.max(0,Math.min(max,next));index=next;if(fade){source.forEach(function(item,i){item.classList.toggle('is-active',i===index);});}else{var item=source[index];if(item){var left=item.offsetLeft-(track===node?node.offsetLeft:track.offsetLeft);if(config.centered)left-=Math.max(0,(node.clientWidth-item.offsetWidth)/2);node.scrollTo({left:Math.max(0,left),behavior:instant?'auto':'smooth'});}}if(config.adaptiveHeight&&source[index]){window.setTimeout(function(){var active=source[index];if(active)node.style.minHeight=Math.ceil(active.getBoundingClientRect().height)+'px';},instant?0:Math.max(120,number(config.speed,550)));}updatePagination();}
 if(config.navigation!==false){var prev=button('Previous slide','cresco-pro-carousel__nav cresco-pro-carousel__prev'),next=button('Next slide','cresco-pro-carousel__nav cresco-pro-carousel__next');prev.textContent='‹';next.textContent='›';prev.addEventListener('click',function(){go(index-1);});next.addEventListener('click',function(){go(index+1);});node.appendChild(prev);node.appendChild(next);}
 if(config.pagination&&config.pagination!=='none'){var pager=document.createElement('div');pager.className='cresco-pro-carousel__pagination';if(config.pagination==='fraction'){var fraction=document.createElement('span');fraction.className='cresco-pro-carousel__fraction';pager.appendChild(fraction);}else{source.forEach(function(_,i){var dot=button('Go to slide '+(i+1),'cresco-pro-carousel__dot');dot.addEventListener('click',function(){go(i);});pager.appendChild(dot);});}node.appendChild(pager);}
 if(config.keyboard!==false){node.tabIndex=node.tabIndex>=0?node.tabIndex:0;node.addEventListener('keydown',function(event){if(event.key==='ArrowLeft'){event.preventDefault();go(index-1);}if(event.key==='ArrowRight'){event.preventDefault();go(index+1);}});}
 var timer=0;function start(){if(!config.autoplay||window.matchMedia('(prefers-reduced-motion: reduce)').matches)return;stop();timer=window.setInterval(function(){go(index+1);},Math.max(1000,number(config.autoplayDelay,4500)));}function stop(){if(timer){window.clearInterval(timer);timer=0;}}
 if(config.pauseOnHover!==false){node.addEventListener('mouseenter',stop);node.addEventListener('mouseleave',start);node.addEventListener('focusin',stop);node.addEventListener('focusout',start);}start();go(0,true);
}

function initMarquee(node,config){
 if(node.dataset.crescoProReady)return;node.dataset.crescoProReady='1';node.classList.add('cresco-pro-marquee','is-'+text(config.direction,'left'));node.style.setProperty('--cresco-marquee-duration',Math.max(4,number(config.duration,32))+'s');node.style.setProperty('--cresco-marquee-gap',text(config.gap,'24px'));
 if(config.pauseOnHover!==false)node.classList.add('pause-hover');if(config.pauseOnFocus!==false)node.classList.add('pause-focus');if(config.edgeFade)node.classList.add('has-edge-fade');
 var source=directElements(node);if(!source.length)return;var track=document.createElement('div');track.className='cresco-pro-marquee__track';var a=document.createElement('div'),b=document.createElement('div');a.className='cresco-pro-marquee__group';b.className='cresco-pro-marquee__group';source.forEach(function(item){a.appendChild(item);b.appendChild(item.cloneNode(true));});track.appendChild(a);track.appendChild(b);node.appendChild(track);
}

function initBeforeAfter(node,config){
 if(node.dataset.crescoProReady)return;node.dataset.crescoProReady='1';node.classList.add('cresco-pro-before-after');var figures=directElements(node);if(figures.length<2)return;figures[0].classList.add('is-before');figures[1].classList.add('is-after');var range=document.createElement('input');range.type='range';range.min='0';range.max='100';range.value=String(number(config.position,50));range.className='cresco-pro-before-after__range';range.setAttribute('aria-label','Before and after image position');function apply(){node.style.setProperty('--cresco-before-after',range.value+'%');}range.addEventListener('input',apply);node.appendChild(range);apply();node.style.aspectRatio=text(config.aspectRatio,'16 / 9');
}

function initCountdown(node,config){
 if(node.dataset.crescoProReady)return;node.dataset.crescoProReady='1';node.classList.add('cresco-pro-countdown');var target=Date.parse(text(config.targetDate,''));function render(){var diff=target-Date.now();if(!Number.isFinite(target)||diff<=0){node.textContent=text(config.expiredText,'Expired');return false;}var seconds=Math.floor(diff/1000),days=Math.floor(seconds/86400);seconds%=86400;var hours=Math.floor(seconds/3600);seconds%=3600;var minutes=Math.floor(seconds/60);seconds%=60;var parts=[];if(config.showDays!==false)parts.push([days,'Days']);parts.push([hours,'Hours'],[minutes,'Minutes']);if(config.showSeconds!==false)parts.push([seconds,'Seconds']);node.innerHTML='';parts.forEach(function(part){var item=document.createElement('span');item.className='cresco-pro-countdown__unit';item.innerHTML='<strong>'+String(part[0]).padStart(2,'0')+'</strong><small>'+part[1]+'</small>';node.appendChild(item);});return true;}if(render())window.setInterval(render,1000);
}

function initAnimatedHeadline(node,config){
 if(node.dataset.crescoProReady)return;node.dataset.crescoProReady='1';node.classList.add('cresco-pro-animated-headline','effect-'+text(config.effect,'fade'));var words=Array.isArray(config.words)?config.words.filter(Boolean):[],prefix=document.createElement('span'),word=document.createElement('span'),suffix=document.createElement('span');prefix.textContent=text(config.prefix,'');suffix.textContent=text(config.suffix,'');word.className='cresco-pro-animated-headline__word';word.textContent=words[0]||'';node.replaceChildren(prefix,word,suffix);if(words.length<2||window.matchMedia('(prefers-reduced-motion: reduce)').matches)return;var i=0;window.setInterval(function(){word.classList.add('is-changing');window.setTimeout(function(){i=(i+1)%words.length;word.textContent=words[i];word.classList.remove('is-changing');},180);},Math.max(500,number(config.interval,2200)));
}

function initProgressCircle(node,config){
 if(node.dataset.crescoProReady)return;node.dataset.crescoProReady='1';node.classList.add('cresco-pro-progress-circle');var value=Math.max(0,Math.min(100,number(config.value,75)));node.style.setProperty('--cresco-progress-value',value+'%');node.style.setProperty('--cresco-progress-size',text(config.size,'140px'));node.style.setProperty('--cresco-progress-thickness',text(config.thickness,'10px'));node.setAttribute('role','progressbar');node.setAttribute('aria-valuemin','0');node.setAttribute('aria-valuemax','100');node.setAttribute('aria-valuenow',String(value));node.innerHTML='<span><strong>'+(config.showValue===false?'':Math.round(value)+'%')+'</strong><small>'+text(config.label,'Progress')+'</small></span>';
}

function initRating(node,config){
 if(node.dataset.crescoProReady)return;node.dataset.crescoProReady='1';node.classList.add('cresco-pro-rating');var max=Math.max(1,number(config.max,5)),value=Math.max(0,Math.min(max,number(config.value,0))),full=Math.round(value);node.setAttribute('role','img');node.setAttribute('aria-label',text(config.label,'Rating')+': '+value+' out of '+max);node.textContent='★'.repeat(full)+'☆'.repeat(Math.max(0,max-full));
}

function initComparison(node,config){
 if(node.dataset.crescoProReady)return;node.dataset.crescoProReady='1';node.classList.add('cresco-pro-comparison');var rows=Array.isArray(config.rows)?config.rows:[];var table=document.createElement('table');rows.forEach(function(row,r){var tr=document.createElement('tr');String(row).split('|').forEach(function(cell){var el=document.createElement(r===0?'th':'td');el.textContent=cell.trim();tr.appendChild(el);});table.appendChild(tr);});if(config.striped!==false)table.classList.add('is-striped');if(config.firstColumn!==false)table.classList.add('first-column');node.replaceChildren(table);
}

function initSearch(node,config){
 if(node.dataset.crescoProReady)return;node.dataset.crescoProReady='1';node.classList.add('cresco-pro-site-search');var form=document.createElement('form');form.method='get';form.action='/';form.setAttribute('role','search');form.setAttribute('aria-label',text(config.ariaLabel,'Search site'));var input=document.createElement('input');input.type='search';input.name='s';input.placeholder=text(config.placeholder,'Search…');var submit=document.createElement('button');submit.type='submit';submit.textContent=text(config.buttonLabel,'Search');form.appendChild(input);form.appendChild(submit);node.replaceChildren(form);
}

function initMap(node,config){
 if(node.dataset.crescoProReady)return;node.dataset.crescoProReady='1';node.classList.add('cresco-pro-map');var address=text(config.address,'').trim();if(!address){node.textContent='Add a map address.';return;}var iframe=document.createElement('iframe');iframe.loading='lazy';iframe.referrerPolicy='no-referrer-when-downgrade';iframe.title=text(config.title,'Map location');iframe.src='https://www.google.com/maps?q='+encodeURIComponent(address)+'&z='+Math.max(1,Math.min(20,number(config.zoom,14)))+'&output=embed';iframe.style.height=text(config.height,'420px');node.replaceChildren(iframe);
}

function initHotspot(node,config){if(node.dataset.crescoProReady)return;node.dataset.crescoProReady='1';node.classList.add('cresco-pro-hotspot');if(config.image)node.style.backgroundImage='url("'+String(config.image).replace(/"/g,'%22')+'")';node.style.aspectRatio=text(config.aspectRatio,'16 / 9');node.setAttribute('role','img');if(config.alt)node.setAttribute('aria-label',text(config.alt,''));}

function initFlip(node,config){
 if(node.dataset.crescoProReady)return;node.dataset.crescoProReady='1';node.classList.add('cresco-pro-flip-card','axis-'+text(config.axis,'y'));node.style.setProperty('--cresco-flip-duration',Math.max(100,number(config.duration,600))+'ms');var children=directElements(node);if(children.length<2)return;var inner=document.createElement('div');inner.className='cresco-pro-flip-card__inner';children[0].classList.add('cresco-pro-flip-card__face','is-front');children[1].classList.add('cresco-pro-flip-card__face','is-back');inner.appendChild(children[0]);inner.appendChild(children[1]);node.appendChild(inner);var trigger=text(config.trigger,'hover');node.classList.add('trigger-'+trigger);if(trigger==='click'){node.tabIndex=0;node.setAttribute('role','button');node.setAttribute('aria-pressed','false');node.addEventListener('click',function(){var on=node.classList.toggle('is-flipped');node.setAttribute('aria-pressed',on?'true':'false');});}
}

function initDialog(node,config,offcanvas){
 if(node.dataset.crescoProReady)return;node.dataset.crescoProReady='1';node.classList.add(offcanvas?'cresco-pro-offcanvas':'cresco-pro-modal');var children=directElements(node),trigger=button(text(config.triggerLabel,offcanvas?'Open panel':'Open popup'),'cresco-pro-dialog__trigger'),backdrop=document.createElement('div'),panel=document.createElement('div'),close=button(text(config.closeLabel,'Close'),'cresco-pro-dialog__close');backdrop.className='cresco-pro-dialog__backdrop';panel.className='cresco-pro-dialog__panel';panel.setAttribute('role','dialog');panel.setAttribute('aria-modal','true');panel.tabIndex=-1;children.forEach(function(child){panel.appendChild(child);});panel.appendChild(close);backdrop.appendChild(panel);node.appendChild(trigger);node.appendChild(backdrop);if(offcanvas){node.dataset.side=text(config.side,'right');node.style.setProperty('--cresco-offcanvas-size',text(config.panelSize,'380px'));}else node.style.setProperty('--cresco-modal-max',text(config.maxWidth,'720px'));
 function open(){node.classList.add('is-open');document.body.classList.add('cresco-pro-dialog-open');panel.focus();}function shut(){node.classList.remove('is-open');document.body.classList.remove('cresco-pro-dialog-open');trigger.focus();}trigger.addEventListener('click',open);close.addEventListener('click',shut);if(config.closeBackdrop!==false)backdrop.addEventListener('mousedown',function(event){if(event.target===backdrop)shut();});if(config.closeOnEsc!==false)node.addEventListener('keydown',function(event){if(event.key==='Escape')shut();});
}

function initTimeline(node,config){if(node.dataset.crescoProReady)return;node.dataset.crescoProReady='1';node.classList.add('cresco-pro-timeline','is-'+text(config.orientation,'vertical'));node.style.setProperty('--cresco-pro-gap',text(config.gap,'24px'));if(config.showLine!==false)node.classList.add('has-line');directElements(node).forEach(function(child){child.classList.add('cresco-pro-timeline__item');});}
function initPricing(node,config){if(node.dataset.crescoProReady)return;node.dataset.crescoProReady='1';node.classList.add('cresco-pro-pricing');node.style.setProperty('--cresco-pricing-cols',String(Math.max(1,number(config.columns,3))));directElements(node).forEach(function(child){child.classList.add('cresco-pro-pricing__item');});}

function init(node){var type=node.getAttribute('data-cresco-pro-widget'),config=decode(node);if(['carousel','slides','loop-carousel','image-carousel','testimonial-carousel','logo-carousel','media-carousel'].indexOf(type)!==-1){if(type==='logo-carousel'&&config.grayscale)node.classList.add('is-grayscale');initCarousel(node,config);return;}if(type==='marquee'){initMarquee(node,config);return;}if(type==='before-after'){initBeforeAfter(node,config);return;}if(type==='countdown'){initCountdown(node,config);return;}if(type==='animated-headline'){initAnimatedHeadline(node,config);return;}if(type==='progress-circle'){initProgressCircle(node,config);return;}if(type==='rating'){initRating(node,config);return;}if(type==='comparison-table'){initComparison(node,config);return;}if(type==='site-search'){initSearch(node,config);return;}if(type==='map'){initMap(node,config);return;}if(type==='hotspot-image'){initHotspot(node,config);return;}if(type==='flip-card'){initFlip(node,config);return;}if(type==='modal'){initDialog(node,config,false);return;}if(type==='off-canvas'){initDialog(node,config,true);return;}if(type==='timeline'){initTimeline(node,config);return;}if(type==='pricing-table'){initPricing(node,config);return;}if(type==='advanced-breadcrumbs')node.classList.add('cresco-pro-breadcrumbs');}
function boot(){all('[data-cresco-pro-widget]').forEach(init);}if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
})(window,document);
