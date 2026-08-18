import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const studioPath = path.join(root, 'runtime-src/build/website-builder-studio.js');
const buildStudioPath = path.join(root, 'build/website-builder-studio.js');
const dimensionPath = path.join(root, 'runtime-src/build/studio-dimension-controls.js');
const buildDimensionPath = path.join(root, 'build/studio-dimension-controls.js');
const cssPath = path.join(root, 'assets/css/website-builder-studio.css');

function replaceOnce(source, before, after, label) {
  if (!source.includes(before)) throw new Error(`Missing ${label}`);
  if (source.indexOf(before) !== source.lastIndexOf(before)) throw new Error(`Ambiguous ${label}`);
  return source.replace(before, after);
}

function replaceBetween(source, start, end, replacement, label) {
  const a = source.indexOf(start);
  const b = source.indexOf(end, a + start.length);
  if (a < 0 || b < 0 || b <= a) throw new Error(`Could not locate ${label}`);
  return source.slice(0, a) + replacement + '\n' + source.slice(b);
}

let studio = fs.readFileSync(studioPath, 'utf8');

studio = replaceOnce(
  studio,
  "function Field(p){return h('label',{className:'cc-studio-field'},h('span',{className:'cc-studio-field__label'},p.label,p.source?h('small',{className:'cc-studio-source is-'+p.source},p.source):null),p.children)}",
  "function Field(p){var attrs=Object.assign({className:'cc-studio-field'},p.attrs||{});return h('label',attrs,h('span',{className:'cc-studio-field__label'},p.label,p.source?h('small',{className:'cc-studio-source is-'+p.source},p.source):null),p.children,p.help?h('small',{className:'cc-studio-field__help'},p.help):null)}",
  'Field helper'
);

const controlBlock = String.raw`
function schemaPanel(schema){var panel=String(schema&&schema.panel||'content').toLowerCase();return['content','layout','style','advanced'].indexOf(panel)>=0?panel:'content'}
function schemaVisible(schema,values){var condition=obj(schema&&schema.condition);if(!condition.key)return true;var actual=obj(values)[condition.key];if(Object.prototype.hasOwnProperty.call(condition,'equals'))return String(actual)===String(condition.equals);if(Object.prototype.hasOwnProperty.call(condition,'notEquals'))return String(actual)!==String(condition.notEquals);if(Array.isArray(condition.in))return condition.in.map(String).indexOf(String(actual))>=0;if(Array.isArray(condition.notIn))return condition.notIn.map(String).indexOf(String(actual))<0;return true}
function humanize(value){return String(value||'').replace(/([a-z0-9])([A-Z])/g,'$1 $2').replace(/[-_]+/g,' ').replace(/^./,function(c){return c.toUpperCase()})}
function propEntries(def,panelName){var props=obj(def&&def.props),values=primary?obj(primary.props):{};return Object.keys(props).map(function(key){return{key:key,schema:props[key]}}).filter(function(item){return schemaPanel(item.schema)===panelName&&schemaVisible(item.schema,values)})}
function propGroups(def,panelName){var groups={};propEntries(def,panelName).forEach(function(item){var group=String(item.schema.group||humanize(panelName)||'General');groups[group]=groups[group]||[];groups[group].push(item)});return groups}
function ownedStyleKeys(def){var owned={};Object.keys(obj(def&&def.props)).forEach(function(key){var styleKey=obj(def.props[key]).styleKey;if(styleKey)owned[String(styleKey)]=true});return owned}
function styleKeysFor(tabName){var defs=selectedNodes.length?selectedNodes.map(function(node){return catalog[node.type]||{}}):[catalog[primary&&primary.type]||{}],owned={};defs.forEach(function(def){Object.assign(owned,ownedStyleKeys(def))});return arr(STYLE_GROUPS[tabName]).filter(function(key){return!owned[key]&&defs.every(function(def){return arr(def.style).indexOf(key)>=0})})}
function statesForSelection(){var defs=selectedNodes.length?selectedNodes.map(function(node){return catalog[node.type]||{}}):[catalog[primary&&primary.type]||{}];var states=['normal'];STATES.slice(1).forEach(function(state){if(defs.length&&defs.every(function(def){return arr(def.states).indexOf(state)>=0}))states.push(state)});return states}
function tabsForSelection(){if(!primary)return[];var def=catalog[primary.type]||{},tabs=[];if(selectedIds.length===1&&propEntries(def,'content').length)tabs.push('content');if((selectedIds.length===1&&propEntries(def,'layout').length)||styleKeysFor('layout').length)tabs.push('layout');if((selectedIds.length===1&&propEntries(def,'style').length)||styleKeysFor('style').length)tabs.push('style');if((selectedIds.length===1&&propEntries(def,'advanced').length)||styleKeysFor('advanced').length||selectedIds.length===1)tabs.push('advanced');return tabs.length?tabs:['advanced']}
function coerceProp(schema,value){if(schema.type==='int'){var i=parseInt(value,10);return Number.isFinite(i)?i:Number(schema.default||schema.min||0)}if(schema.type==='number'){var n=parseFloat(value);return Number.isFinite(n)?n:Number(schema.default||schema.min||0)}return value}
function optionItems(schema){return arr(options[schema.optionsSource])}
function optionValue(item,schema){var key=schema.optionValue||((item&&Object.prototype.hasOwnProperty.call(item,'id'))?'id':(item&&Object.prototype.hasOwnProperty.call(item,'slug'))?'slug':'value');return item&&Object.prototype.hasOwnProperty.call(item,key)?item[key]:''}
function optionLabel(item,schema){var key=schema.optionLabel||'label';return item&&Object.prototype.hasOwnProperty.call(item,key)?item[key]:optionValue(item,schema)}
function listControl(key,schema,value){var values=arr(value);function update(next){prop(key,next)}return h('div',{className:'cc-studio-repeater','data-cresco-repeater-shape':schema.shape||'string_list'},values.map(function(item,index){return h('div',{key:index,className:'cc-studio-repeater-row'},h('input',{value:String(item==null?'':item),placeholder:schema.placeholder||'Item',onChange:function(e){var next=values.slice();next[index]=e.target.value;update(next)}}),iconButton('arrow-up-alt2','Move up',function(){if(index<1)return;var next=values.slice(),hold=next[index-1];next[index-1]=next[index];next[index]=hold;update(next)},{disabled:index<1}),iconButton('arrow-down-alt2','Move down',function(){if(index>=values.length-1)return;var next=values.slice(),hold=next[index+1];next[index+1]=next[index];next[index]=hold;update(next)},{disabled:index>=values.length-1}),iconButton('trash','Remove',function(){update(values.filter(function(_,i){return i!==index}))},{className:'cc-studio-icon-btn is-danger'}))}),h('button',{type:'button',className:'cc-studio-button is-secondary',onClick:function(){update(values.concat(['']))}},icon('plus-alt2'),' Add item'))}
function repeaterTemplate(schema){var defaults=arr(schema.default);if(defaults.length&&defaults[0]&&typeof defaults[0]==='object')return clone(defaults[0]);var shape=String(schema.shape||'');if(shape==='gallery')return{url:'',alt:'',caption:''};if(shape==='accordion')return{title:'Item',content:'',open:false};if(shape==='tabs')return{title:'Tab',content:''};if(shape==='social')return{label:'Website',url:'#',icon:'admin-site'};if(shape==='form_fields')return{name:'field',label:'Field',type:'text',required:false};return{label:'Item'}}
function repeaterItemControl(shape,field,value,onChange){if(typeof value==='boolean')return h('input',{type:'checkbox',checked:!!value,onChange:function(e){onChange(e.target.checked)}});if(typeof value==='number')return h('input',{type:'number',step:'any',value:value,onChange:function(e){onChange(parseFloat(e.target.value||'0'))}});if(shape==='form_fields'&&field==='type')return h('select',{value:String(value||'text'),onChange:function(e){onChange(e.target.value)}},['text','email','tel','url','number','textarea','select','checkbox','radio','date'].map(function(type){return h('option',{key:type,value:type},type)}));if(/^(url|image|avatar|poster)$/i.test(field))return h('div',{className:'cc-studio-input-action'},h('input',{type:'url',value:String(value||''),onChange:function(e){onChange(e.target.value)}}),iconButton('format-image','Choose media',function(){chooseMedia(function(media){onChange(media.url||'')})}));if(/icon/i.test(field))return h('div',{className:'cc-studio-icon-control'},h('span',{className:'dashicons dashicons-'+String(value||'admin-generic'),'aria-hidden':'true'}),h('input',{value:String(value||''),onChange:function(e){onChange(e.target.value)},placeholder:'Dashicon name'}));if(/content|description|message|caption|quote/i.test(field))return h('textarea',{rows:3,value:String(value||''),onChange:function(e){onChange(e.target.value)}});return h('input',{value:String(value==null?'':value),onChange:function(e){onChange(e.target.value)}})}
function jsonRepeaterControl(key,schema,value){var values=arr(value),shape=String(schema.shape||'');function update(next){prop(key,next)}return h('div',{className:'cc-studio-repeater','data-cresco-repeater-shape':shape},values.map(function(item,index){var row=obj(item);return h('article',{key:index,className:'cc-studio-repeater-card'},h('header',null,h('strong',null,(schema.label||key)+' '+(index+1)),h('span',null,iconButton('arrow-up-alt2','Move up',function(){if(index<1)return;var next=values.slice(),hold=next[index-1];next[index-1]=next[index];next[index]=hold;update(next)},{disabled:index<1}),iconButton('arrow-down-alt2','Move down',function(){if(index>=values.length-1)return;var next=values.slice(),hold=next[index+1];next[index+1]=next[index];next[index]=hold;update(next)},{disabled:index>=values.length-1}),iconButton('trash','Remove',function(){update(values.filter(function(_,i){return i!==index}))},{className:'cc-studio-icon-btn is-danger'}))),Object.keys(row).map(function(field){return h('label',{key:field,className:'cc-studio-repeater-field'},h('small',null,humanize(field)),repeaterItemControl(shape,field,row[field],function(nextValue){var next=values.map(function(entry,i){return i===index?Object.assign({},obj(entry),{[field]:nextValue}):entry});update(next)}))}))}),h('button',{type:'button',className:'cc-studio-button is-secondary',onClick:function(){update(values.concat([repeaterTemplate(schema)]))}},icon('plus-alt2'),' Add '+(schema.label||'item')))}
function rawJsonControl(key,schema,value){return h('textarea',{rows:7,defaultValue:JSON.stringify(value==null?schema.default:value,null,2),onBlur:function(e){var parsed=safe(e.target.value,null);if(parsed===null){setNotice({tone:'warning',text:(schema.label||key)+' contains invalid JSON and was not applied.'});return}prop(key,parsed)}})}
function renderPropGroups(def,panelName){var groups=propGroups(def,panelName),names=Object.keys(groups);if(!names.length)return null;return h('div',{className:'cc-studio-control-groups','data-cresco-prop-panel':panelName},names.map(function(name){var items=groups[name];if(!items.length)return null;return h('section',{key:name,className:'cc-studio-control-group'},h('h3',null,name),h('div',{className:'cc-studio-fields'},items.map(function(item){return h(Fragment,{key:item.key},propControl(item.key,item.schema))})))}))}
useEffect(function(){if(!primary)return;var states=statesForSelection(),tabs=tabsForSelection();if(states.indexOf(activeState)<0)setActiveState('normal');if(tabs.indexOf(tab)<0)setTab(tabs[0]||'advanced')},[primary&&primary.id,selectedIds.join(','),extensionRevision]);
function propControl(key,schema){if(!primary||!schemaVisible(schema,obj(primary.props)))return null;var value=obj(primary.props)[key],type=schema.type||'string',kind=String(schema.control||''),control,attrs={'data-cresco-prop-key':key,'data-cresco-control':kind||type};if(kind==='repeater'&&type==='json')return h('div',Object.assign({className:'cc-studio-field cc-studio-field--repeater'},attrs),h('span',{className:'cc-studio-field__label'},schema.label||humanize(key)),jsonRepeaterControl(key,schema,value),schema.help?h('small',{className:'cc-studio-field__help'},schema.help):null);if(kind==='repeater'&&type==='string_list')return h('div',Object.assign({className:'cc-studio-field cc-studio-field--repeater'},attrs),h('span',{className:'cc-studio-field__label'},schema.label||humanize(key)),listControl(key,schema,value),schema.help?h('small',{className:'cc-studio-field__help'},schema.help):null);if(type==='bool'||kind==='toggle')control=h('input',{type:'checkbox',checked:!!value,onChange:function(e){prop(key,e.target.checked)}});else if(type==='enum'||kind==='select'){var labels=obj(schema.valueLabels);control=h('select',{value:value==null?'':String(value),onChange:function(e){prop(key,coerceProp(schema,e.target.value))}},arr(schema.values).map(function(v){return h('option',{key:v,value:v},labels[v]||v)}))}else if(kind==='option-select'){var items=optionItems(schema);if(items.length)control=h('select',{value:value==null?'':String(value),onChange:function(e){prop(key,coerceProp(schema,e.target.value))}},[h('option',{key:'',value:''},schema.emptyLabel||'Choose an option')].concat(items.map(function(item,index){var v=optionValue(item,schema);return h('option',{key:String(v)+'-'+index,value:v},optionLabel(item,schema))})));else control=h('input',{value:value==null?'':String(value),placeholder:schema.emptyLabel||schema.placeholder||'No options are available',onChange:function(e){prop(key,coerceProp(schema,e.target.value))}})}else if(type==='int'||type==='number'||kind==='number')control=h('input',{type:'number',min:schema.min,max:schema.max,step:type==='int'?1:'any',value:value==null?'':value,onChange:function(e){var raw=e.target.value;if(raw==='')return;prop(key,coerceProp(schema,raw))},onBlur:function(e){if(e.target.value==='')prop(key,schema.default!=null?schema.default:(schema.min!=null?schema.min:0))}});else if(type==='text'||type==='richtext'||kind==='textarea'||kind==='richtext')control=h('textarea',{rows:type==='richtext'?7:4,value:value==null?'':String(value),placeholder:schema.placeholder||'',onChange:function(e){prop(key,e.target.value)}});else if(type==='json')control=rawJsonControl(key,schema,value);else if(type==='string_list')control=listControl(key,schema,value);else if(kind==='media')control=h('div',{className:'cc-studio-input-action'},h('input',{type:'url',value:value||'',placeholder:schema.placeholder||'Media URL',onChange:function(e){prop(key,e.target.value)}}),iconButton('format-image','Choose media',function(){chooseMedia(function(media){prop(key,media.url||'')})}));else if(type==='url'||kind==='link')control=h('input',{type:'url',value:value||'',placeholder:schema.placeholder||'https://…',onChange:function(e){prop(key,e.target.value)}});else if(kind==='email')control=h('input',{type:'email',value:value||'',placeholder:schema.placeholder||'name@example.com',onChange:function(e){prop(key,e.target.value)}});else if(kind==='icon')control=h('div',{className:'cc-studio-icon-control'},h('span',{className:'dashicons dashicons-'+String(value||'admin-generic'),'aria-hidden':'true'}),h('input',{value:value==null?'':String(value),placeholder:schema.placeholder||'Dashicon name',onChange:function(e){prop(key,e.target.value)}}));else control=h('input',{value:value==null?'':String(value),placeholder:schema.placeholder||'',onChange:function(e){prop(key,e.target.value)}});return Field({label:schema.label||humanize(key),children:control,help:schema.help,attrs:attrs})}
function styleControl(key){if(!primary)return null;var current=effectiveStyle(primary,device,activeState,global)[key]||'',control;if(ENUMS[key])control=h('select',{value:current,onChange:function(e){style(key,e.target.value)}},[h('option',{key:'',value:''},'Inherit')].concat(ENUMS[key].map(function(v){return h('option',{key:v,value:v},v)})));else if(/color/i.test(key))control=h('div',{className:'cc-studio-color'},h('input',{type:'color',value:/^#[0-9a-f]{6}$/i.test(current)?current:'#000000',onChange:function(e){style(key,e.target.value)}}),h('input',{value:current,onChange:function(e){style(key,e.target.value)},placeholder:'#000000 or {colors.primary}'}));else control=h('input',{value:current,onChange:function(e){style(key,e.target.value)},placeholder:'auto / 1rem / 50%'});return h('div',{key:key,className:'cc-studio-style-field','data-cresco-style-key':key},h('div',{className:'cc-studio-style-field__label'},h('span',null,humanize(key)),current?iconButton('undo','Reset',function(){style(key,'')}):null),control)}
function spacing(prefix,keys){var s=effectiveStyle(primary,device,activeState,global),sides=['Top','Right','Bottom','Left'],allowed=new Set(keys||[]),visible=sides.filter(function(side){return allowed.has(prefix.toLowerCase()+side)});if(!visible.length)return null;return h('section',{className:'cc-studio-spacing','data-cresco-spacing-kind':prefix.toLowerCase()},h('strong',null,prefix),h('div',{className:'cc-studio-spacing__grid'},visible.map(function(side){var key=prefix.toLowerCase()+side;return h('label',{key:key,'data-cresco-spacing-key':key},h('small',null,side[0]),h('input',{value:s[key]||'',onChange:function(e){style(key,e.target.value)},placeholder:'0px'}))})))}
function inspector(){if(!primary)return h('section',{className:'cc-studio-panel cc-studio-empty'},icon('edit'),h('strong',null,'Select a widget'),h('p',null,'Select a widget on Canvas or in Structure.'));var def=catalog[primary.type]||{},multi=selectedIds.length>1,tabs=tabsForSelection(),activeTab=tabs.indexOf(tab)>=0?tab:tabs[0],states=statesForSelection(),layoutKeys=styleKeysFor('layout'),styleKeys=styleKeysFor('style'),advancedKeys=styleKeysFor('advanced'),marginKeys=advancedKeys.filter(function(k){return/^margin/.test(k)}),paddingKeys=advancedKeys.filter(function(k){return/^padding/.test(k)}),advancedNonSpacing=advancedKeys.filter(function(k){return!/^margin|^padding/.test(k)});return h('section',{className:'cc-studio-panel'},h('header',{className:'cc-studio-panel-head'},h('div',null,h('strong',null,multi?selectedIds.length+' widgets selected':'Edit '+(def.label||primary.type)),h('small',null,multi?'Bulk style editing':primary.id)),h('span',{className:'cc-studio-panel-actions'},iconButton('clipboard','Copy styles',copyStyles),iconButton('download','Paste styles',pasteStyles),iconButton('admin-page','Duplicate',function(){duplicate()}),iconButton('trash','Delete',function(){remove()},{className:'cc-studio-icon-btn is-danger'}))),!multi?h('div',{className:'cc-studio-meta-grid'},Field({label:'Navigator label',children:h('input',{value:primary.meta&&primary.meta.label||'',onChange:function(e){meta([primary.id],'label',e.target.value)}})}),h('label',{className:'cc-studio-switch-row'},h('span',null,'Lock'),h('input',{type:'checkbox',checked:!!(primary.meta&&primary.meta.locked),onChange:function(e){meta([primary.id],'locked',e.target.checked)}})),h('label',{className:'cc-studio-switch-row'},h('span',null,device==='wide'?'Hidden':'Hidden on '+device),h('input',{type:'checkbox',checked:device==='wide'?!!(primary.meta&&primary.meta.hidden):effectiveStyle(primary,device,activeState,global).visibility==='hidden',onChange:function(e){device==='wide'?meta([primary.id],'hidden',e.target.checked):style('visibility',e.target.checked?'hidden':'visible')}}))):null,h('div',{className:'cc-studio-context-row'},h('div',{className:'cc-studio-device-tabs'},DEVICES.map(function(d){return iconButton(ICONS[d],d,function(){setDevice(d)},{key:d,className:'cc-studio-icon-btn'+(device===d?' is-active':'')})})),states.length>1?h('div',{className:'cc-studio-state-tabs'},states.map(function(s){return h('button',{key:s,type:'button',className:activeState===s?' is-active':'',onClick:function(){setActiveState(s)}},s)})):null,device!=='wide'?h('span',null,iconButton('controls-repeat','Copy previous breakpoint',copyBreakpoint),iconButton('undo','Reset breakpoint',resetBreakpoint)):null),tabs.length>1?h('nav',{className:'cc-studio-inspector-tabs'},tabs.map(function(t){return h('button',{key:t,type:'button',className:activeTab===t?'is-active':'',onClick:function(){setTab(t)}},t)})):null,activeTab==='content'?h(Fragment,null,renderPropGroups(def,'content'),h('div',{className:'cc-studio-inline-actions'},h('button',{type:'button',onClick:function(){setPanel('dynamic')}},icon('database'),' Dynamic data'),h('button',{type:'button',onClick:function(){setPanel('ai');setAiScope('widget')}},icon('superhero'),' Ask AI'))):activeTab==='layout'?h(Fragment,null,!multi?renderPropGroups(def,'layout'):null,layoutKeys.length?h('div',{className:'cc-studio-style-grid'},layoutKeys.map(styleControl)):null):activeTab==='style'?h(Fragment,null,!multi?renderPropGroups(def,'style'):null,styleKeys.length?h('div',{className:'cc-studio-style-grid'},styleKeys.map(styleControl)):null):h(Fragment,null,!multi?renderPropGroups(def,'advanced'):null,h('div',{className:'cc-studio-style-grid'},spacing('Margin',marginKeys),spacing('Padding',paddingKeys),advancedNonSpacing.map(styleControl),!multi?h('section',{className:'cc-studio-custom-css'},h('h3',null,'Scoped Custom CSS'),h('textarea',{rows:9,value:obj(primary.customCSS)[device==='wide'?'base':device]||'',onChange:function(e){updateSelected(function(n){var css=Object.assign({},n.customCSS||{}),k=device==='wide'?'base':device;if(e.target.value)css[k]=e.target.value;else delete css[k];n.customCSS=css;return n},'custom-css')}})):null)),arr(window.CrescoStudioSDK.getRegistry('inspectorSections')).filter(function(x){return!x.when||x.when({node:primary,selectedNodes:selectedNodes,device:device,state:activeState,tab:activeTab})}).map(function(x){return h('section',{key:x.id,className:'cc-studio-extension-section'},h('h3',null,x.label||x.id),typeof x.render==='function'?x.render({node:clone(primary),device:device,state:activeState,dispatch:window.CrescoStudioSDK.dispatch}):x.content)}))}
`;

studio = replaceBetween(studio, 'function propControl(key,schema){', 'function widgets(){', controlBlock, 'Inspector control block');
fs.writeFileSync(studioPath, studio);
fs.writeFileSync(buildStudioPath, studio);

let dimension = fs.readFileSync(dimensionPath, 'utf8');
dimension = replaceBetween(
  dimension,
  '  function styleField(key) {',
  '  function nativeValue(control, value) {',
  String.raw`  function styleField(key) {
    var panel = inspectorPanel();
    if (!panel) return null;
    return panel.querySelector('.cc-studio-style-field[data-cresco-style-key="' + String(key).replace(/"/g, '\\"') + '"]') || null;
  }
  function sourceControl(key) {
    var field = styleField(key);
    if (!field) return null;
    return all('input,select,textarea', field).find(function (control) { return control.type !== 'color'; }) || null;
  }
  function spacingSection(kind) {
    var panel = inspectorPanel();
    if (!panel) return null;
    return panel.querySelector('.cc-studio-spacing[data-cresco-spacing-kind="' + String(kind).replace(/"/g, '\\"') + '"]') || null;
  }
  function spacingSource(kind, index) {
    var section = spacingSection(kind);
    if (!section) return null;
    var labels = all(':scope > .cc-studio-spacing__grid > label', section);
    return labels[index] ? labels[index].querySelector('input') : null;
  }
  function definition(node) { return node && catalog[node.type] ? catalog[node.type] : null; }
  function dimensionProps(node) {
    var def = definition(node);
    var props = obj(def && def.props);
    return Object.keys(props).filter(function (key) {
      var schema = props[key];
      if (!schema || schema.type !== 'css' || key === 'aspectRatio' || /ratio/i.test(key)) return false;
      return DIMENSION_PROP_PATTERN.test(key) || /(width|height|gap|spacing|size|thickness|radius|offset|inset)/i.test(schema.label || '');
    });
  }
  function propField(node, key) {
    var panel = inspectorPanel();
    if (!panel) return null;
    return panel.querySelector('.cc-studio-field[data-cresco-prop-key="' + String(key).replace(/"/g, '\\"') + '"]') || null;
  }
  function propSource(node, key) {
    var field = propField(node, key);
    if (!field) return null;
    return all('input,select,textarea', field).find(function (control) { return control.type !== 'color'; }) || null;
  }`,
  'dimension source lookup'
);
fs.writeFileSync(dimensionPath, dimension);
fs.writeFileSync(buildDimensionPath, dimension);

let css = fs.readFileSync(cssPath, 'utf8');
const marker = '/* CRESCO_WIDGET_CONTROL_INTEGRITY_V1 */';
if (!css.includes(marker)) {
  css += String.raw`

/* CRESCO_WIDGET_CONTROL_INTEGRITY_V1 */
.cc-studio-control-groups{display:grid;gap:10px;min-width:0}
.cc-studio-control-group{display:grid;gap:8px;min-width:0;padding:10px;border:1px solid var(--cc-color-border,#e4e7ec);border-radius:10px;background:var(--cc-color-surface,#fff)}
.cc-studio-control-group>h3{margin:0;font-size:11px;font-weight:700;color:var(--cc-color-text,#172033)}
.cc-studio-field__help{display:block;margin-top:5px;color:var(--cc-color-text-muted,#667085);font-size:9px;line-height:1.4}
.cc-studio-field--repeater{display:grid;gap:7px;min-width:0}
.cc-studio-repeater{display:grid;gap:7px;min-width:0}
.cc-studio-repeater-row{display:grid;grid-template-columns:minmax(0,1fr) repeat(3,28px);gap:5px;align-items:center;min-width:0}
.cc-studio-repeater-card{display:grid;gap:7px;min-width:0;padding:8px;border:1px solid var(--cc-color-border,#e4e7ec);border-radius:8px;background:var(--cc-color-surface-subtle,#f8f9fc)}
.cc-studio-repeater-card>header{display:flex;align-items:center;justify-content:space-between;gap:8px;min-width:0}
.cc-studio-repeater-card>header>span{display:flex;gap:4px;flex:0 0 auto}
.cc-studio-repeater-field{display:grid;grid-template-columns:90px minmax(0,1fr);align-items:center;gap:8px;min-width:0}
.cc-studio-repeater-field>small{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--cc-color-text-subtle,#475467);font-size:9px}
.cc-studio-repeater-field input,.cc-studio-repeater-field select,.cc-studio-repeater-field textarea,.cc-studio-control-group input,.cc-studio-control-group select,.cc-studio-control-group textarea{min-width:0;max-width:100%;box-sizing:border-box}
.cc-studio-icon-control{display:grid;grid-template-columns:28px minmax(0,1fr);gap:6px;align-items:center;min-width:0}
.cc-studio-icon-control>.dashicons{display:grid;place-items:center;width:28px;height:28px;border:1px solid var(--cc-color-border,#e4e7ec);border-radius:7px;background:var(--cc-color-surface-subtle,#f8f9fc)}
@container (max-width:330px){.cc-studio-repeater-field{grid-template-columns:1fr}.cc-studio-repeater-row{grid-template-columns:minmax(0,1fr) repeat(3,26px)}}
`;
  fs.writeFileSync(cssPath, css);
}

console.log('Applied WidgetCatalog-driven Inspector control integrity patch.');
