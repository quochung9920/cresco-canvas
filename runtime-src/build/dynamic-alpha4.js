(function(wp){
'use strict';
if(!wp||!wp.blocks||!wp.blockEditor||!wp.components||!wp.element||!wp.i18n||!wp.apiFetch){return;}
var el=wp.element.createElement;
var Fragment=wp.element.Fragment;
var useEffect=wp.element.useEffect;
var useState=wp.element.useState;
var __=wp.i18n.__;
var registerBlockType=wp.blocks.registerBlockType;
var InspectorControls=wp.blockEditor.InspectorControls;
var InnerBlocks=wp.blockEditor.InnerBlocks;
var useBlockProps=wp.blockEditor.useBlockProps;
var PanelBody=wp.components.PanelBody;
var SelectControl=wp.components.SelectControl;
var TextControl=wp.components.TextControl;
var RangeControl=wp.components.RangeControl;
var ToggleControl=wp.components.ToggleControl;
var Notice=wp.components.Notice;
var apiFetch=wp.apiFetch;
var fieldCache=null;
var fieldPromise=null;
var contentBlocks=['core/group','core/columns','core/column','core/heading','core/paragraph','core/list','core/buttons','core/button','core/separator','core/spacer','cresco/acf-sub-field','cresco/dynamic-field','cresco/dynamic-image','cresco/dynamic-gallery'];

function useAcfFields(type){
 var state=useState(fieldCache||[]),fields=state[0],setFields=state[1];
 useEffect(function(){
  if(fieldCache){setFields(fieldCache);return;}
  if(!fieldPromise){fieldPromise=apiFetch({path:'/cresco-canvas/v1/dynamic/acf-fields'}).then(function(result){fieldCache=result&&result.fields?result.fields:[];return fieldCache;}).catch(function(){fieldCache=[];return fieldCache;});}
  fieldPromise.then(setFields);
 },[]);
 return fields.filter(function(field){return !type||field.type===type;});
}

function FieldKeyControl(props){
 var fields=useAcfFields(props.type);
 var options=[{label:__('Select a field','cresco-canvas'),value:''}].concat(fields.map(function(field){return{label:field.label+' ('+field.name+')',value:field.name};}));
 return fields.length?el(SelectControl,{label:props.label||__('ACF field','cresco-canvas'),value:props.value,options:options,onChange:props.onChange}):el(TextControl,{label:props.label||__('Field key','cresco-canvas'),value:props.value,onChange:props.onChange,help:__('ACF field catalog is unavailable; enter the field name manually.','cresco-canvas')});
}

registerBlockType('cresco/acf-sub-field',{
 apiVersion:3,title:__('ACF Sub Field','cresco-canvas'),icon:'editor-code',category:'cresco-canvas',
 description:__('Render a scalar value from the current Repeater or Flexible Content row.','cresco-canvas'),
 parent:['cresco/acf-repeater','cresco/acf-layout'],
 attributes:{fieldPath:{type:'string',default:''},fallback:{type:'string',default:''},tagName:{type:'string',default:'span'}},
 supports:{html:false,color:true,typography:true,spacing:true},
 edit:function(props){var a=props.attributes,set=props.setAttributes;return el(Fragment,null,
  el(InspectorControls,null,el(PanelBody,{title:__('Row field','cresco-canvas'),initialOpen:true},
   el(TextControl,{label:__('Field path','cresco-canvas'),value:a.fieldPath,onChange:function(v){set({fieldPath:v});},help:__('Use a dot path for nested arrays, up to four levels.','cresco-canvas')}),
   el(TextControl,{label:__('Fallback','cresco-canvas'),value:a.fallback,onChange:function(v){set({fallback:v});}}),
   el(SelectControl,{label:__('HTML tag','cresco-canvas'),value:a.tagName,options:['span','p','div','h1','h2','h3','h4','h5','h6'].map(function(v){return{label:v,value:v};}),onChange:function(v){set({tagName:v});}})
  )),
  el('div',useBlockProps({className:'cresco-acf-sub-field is-editor-preview'}),a.fieldPath||__('Choose a row field','cresco-canvas'))
 );},save:function(){return null;}
});

registerBlockType('cresco/acf-repeater',{
 apiVersion:3,title:__('ACF Repeater','cresco-canvas'),icon:'list-view',category:'cresco-canvas',
 description:__('Repeat one native block template for each ACF Repeater row.','cresco-canvas'),
 attributes:{source:{type:'string',default:'acf'},key:{type:'string',default:''},limit:{type:'number',default:12},columns:{type:'number',default:1},emptyMessage:{type:'string',default:''}},
 supports:{html:false,align:['wide','full'],spacing:true},
 edit:function(props){var a=props.attributes,set=props.setAttributes;return el(Fragment,null,
  el(InspectorControls,null,el(PanelBody,{title:__('Repeater source','cresco-canvas'),initialOpen:true},
   el(SelectControl,{label:__('Source','cresco-canvas'),value:a.source,options:[{label:'ACF',value:'acf'},{label:__('Post meta','cresco-canvas'),value:'meta'}],onChange:function(v){set({source:v});}}),
   a.source==='acf'?el(FieldKeyControl,{type:'repeater',value:a.key,onChange:function(v){set({key:v});}}):el(TextControl,{label:__('Meta key','cresco-canvas'),value:a.key,onChange:function(v){set({key:v});}}),
   el(RangeControl,{label:__('Maximum rows','cresco-canvas'),value:a.limit,min:1,max:24,onChange:function(v){set({limit:v});}}),
   el(RangeControl,{label:__('Columns','cresco-canvas'),value:a.columns,min:1,max:6,onChange:function(v){set({columns:v});}}),
   el(TextControl,{label:__('Empty message','cresco-canvas'),value:a.emptyMessage,onChange:function(v){set({emptyMessage:v});}})
  )),
  el('div',useBlockProps({className:'cresco-acf-repeater is-editor-preview'}),
   el(Notice,{status:'info',isDismissible:false},__('The inner template is repeated for every row on the server.','cresco-canvas')),
   el(InnerBlocks,{allowedBlocks:contentBlocks,template:[['core/group',{},[['cresco/acf-sub-field',{fieldPath:'title',tagName:'h3'}],['cresco/acf-sub-field',{fieldPath:'description',tagName:'p'}]]]],templateLock:false})
  )
 );},save:function(){return el(InnerBlocks.Content);}
});

registerBlockType('cresco/acf-layout',{
 apiVersion:3,title:__('ACF Layout Template','cresco-canvas'),icon:'layout',category:'cresco-canvas',
 parent:['cresco/acf-flexible'],
 attributes:{layoutName:{type:'string',default:''}},supports:{html:false,reusable:false},
 edit:function(props){var a=props.attributes,set=props.setAttributes;return el(Fragment,null,
  el(InspectorControls,null,el(PanelBody,{title:__('Flexible layout','cresco-canvas'),initialOpen:true},el(TextControl,{label:__('Layout name','cresco-canvas'),value:a.layoutName,onChange:function(v){set({layoutName:v});},help:__('Use the ACF layout name. Use fallback for unmatched layouts.','cresco-canvas')}))),
  el('div',useBlockProps({className:'cresco-acf-layout is-editor-preview'}),el('strong',null,a.layoutName||__('Unnamed layout','cresco-canvas')),el(InnerBlocks,{allowedBlocks:contentBlocks,templateLock:false}))
 );},save:function(){return el(InnerBlocks.Content);}
});

registerBlockType('cresco/acf-flexible',{
 apiVersion:3,title:__('ACF Flexible Content','cresco-canvas'),icon:'schedule',category:'cresco-canvas',
 description:__('Map ACF Flexible Content layouts to native block templates.','cresco-canvas'),
 attributes:{source:{type:'string',default:'acf'},key:{type:'string',default:''},limit:{type:'number',default:12},emptyMessage:{type:'string',default:''}},
 supports:{html:false,align:['wide','full'],spacing:true},
 edit:function(props){var a=props.attributes,set=props.setAttributes;return el(Fragment,null,
  el(InspectorControls,null,el(PanelBody,{title:__('Flexible source','cresco-canvas'),initialOpen:true},
   el(SelectControl,{label:__('Source','cresco-canvas'),value:a.source,options:[{label:'ACF',value:'acf'},{label:__('Post meta','cresco-canvas'),value:'meta'}],onChange:function(v){set({source:v});}}),
   a.source==='acf'?el(FieldKeyControl,{type:'flexible_content',value:a.key,onChange:function(v){set({key:v});}}):el(TextControl,{label:__('Meta key','cresco-canvas'),value:a.key,onChange:function(v){set({key:v});}}),
   el(RangeControl,{label:__('Maximum layouts','cresco-canvas'),value:a.limit,min:1,max:24,onChange:function(v){set({limit:v});}}),
   el(TextControl,{label:__('Empty message','cresco-canvas'),value:a.emptyMessage,onChange:function(v){set({emptyMessage:v});}})
  )),
  el('div',useBlockProps({className:'cresco-acf-flexible is-editor-preview'}),
   el(Notice,{status:'info',isDismissible:false},__('Add one Layout Template block for each acf_fc_layout value.','cresco-canvas')),
   el(InnerBlocks,{allowedBlocks:['cresco/acf-layout'],template:[['cresco/acf-layout',{layoutName:'hero'}],['cresco/acf-layout',{layoutName:'fallback'}]],templateLock:false})
  )
 );},save:function(){return el(InnerBlocks.Content);}
});

registerBlockType('cresco/advanced-loop',{
 apiVersion:3,title:__('Advanced Loop','cresco-canvas'),icon:'filter',category:'cresco-canvas',
 description:__('Run a bounded post, author, date, taxonomy, and meta query.','cresco-canvas'),
 attributes:{postType:{type:'string',default:'post'},postsPerPage:{type:'number',default:6},order:{type:'string',default:'DESC'},orderby:{type:'string',default:'date'},authorId:{type:'number',default:0},parentId:{type:'number',default:0},search:{type:'string',default:''},dateAfter:{type:'string',default:''},dateBefore:{type:'string',default:''},includeIds:{type:'string',default:''},excludeIds:{type:'string',default:''},metaKey:{type:'string',default:''},metaValue:{type:'string',default:''},metaCompare:{type:'string',default:'='},metaType:{type:'string',default:'CHAR'},taxFilters:{type:'array',default:[]},columns:{type:'number',default:3},pagination:{type:'boolean',default:false},pageParam:{type:'string',default:'cc_advanced_page'},emptyMessage:{type:'string',default:''}},
 supports:{html:false,align:['wide','full'],spacing:true},
 edit:function(props){var a=props.attributes,set=props.setAttributes;function number(v){return parseInt(v,10)||0;}function tax(index,key,value){var filters=(a.taxFilters||[]).slice();while(filters.length<=index){filters.push({taxonomy:'',terms:'',operator:'IN'});}filters[index]=Object.assign({},filters[index]);filters[index][key]=value;set({taxFilters:filters});}return el(Fragment,null,
  el(InspectorControls,null,
   el(PanelBody,{title:__('Content query','cresco-canvas'),initialOpen:true},
    el(TextControl,{label:__('Post type slug','cresco-canvas'),value:a.postType,onChange:function(v){set({postType:v});}}),
    el(RangeControl,{label:__('Items per page','cresco-canvas'),value:a.postsPerPage,min:1,max:24,onChange:function(v){set({postsPerPage:v});}}),
    el(SelectControl,{label:__('Order by','cresco-canvas'),value:a.orderby,options:['date','modified','title','menu_order','rand','meta_value','meta_value_num'].map(function(v){return{label:v,value:v};}),onChange:function(v){set({orderby:v});}}),
    el(SelectControl,{label:__('Order','cresco-canvas'),value:a.order,options:[{label:'DESC',value:'DESC'},{label:'ASC',value:'ASC'}],onChange:function(v){set({order:v});}}),
    el(TextControl,{label:__('Search','cresco-canvas'),value:a.search,onChange:function(v){set({search:v});}}),
    el(TextControl,{label:__('Author ID','cresco-canvas'),type:'number',value:a.authorId,onChange:function(v){set({authorId:number(v)});}}),
    el(TextControl,{label:__('Parent post ID','cresco-canvas'),type:'number',value:a.parentId,onChange:function(v){set({parentId:number(v)});}}),
    el(TextControl,{label:__('Include post IDs','cresco-canvas'),value:a.includeIds,onChange:function(v){set({includeIds:v});},help:__('Comma separated, maximum 24.','cresco-canvas')}),
    el(TextControl,{label:__('Exclude post IDs','cresco-canvas'),value:a.excludeIds,onChange:function(v){set({excludeIds:v});}}),
    el(TextControl,{label:__('Date after','cresco-canvas'),type:'date',value:a.dateAfter,onChange:function(v){set({dateAfter:v});}}),
    el(TextControl,{label:__('Date before','cresco-canvas'),type:'date',value:a.dateBefore,onChange:function(v){set({dateBefore:v});}})
   ),
   el(PanelBody,{title:__('Meta query','cresco-canvas'),initialOpen:false},
    el(TextControl,{label:__('Meta key','cresco-canvas'),value:a.metaKey,onChange:function(v){set({metaKey:v});}}),
    el(TextControl,{label:__('Meta value','cresco-canvas'),value:a.metaValue,onChange:function(v){set({metaValue:v});}}),
    el(SelectControl,{label:__('Compare','cresco-canvas'),value:a.metaCompare,options:['=','!=','>','>=','<','<=','LIKE','NOT LIKE','EXISTS','NOT EXISTS'].map(function(v){return{label:v,value:v};}),onChange:function(v){set({metaCompare:v});}}),
    el(SelectControl,{label:__('Type','cresco-canvas'),value:a.metaType,options:['CHAR','NUMERIC','DATE'].map(function(v){return{label:v,value:v};}),onChange:function(v){set({metaType:v});}})
   ),
   el(PanelBody,{title:__('Taxonomy filters','cresco-canvas'),initialOpen:false},[0,1,2].map(function(i){var filter=(a.taxFilters||[])[i]||{};return el('div',{className:'cresco-alpha4-tax-filter',key:i},el(TextControl,{label:__('Taxonomy slug','cresco-canvas')+' '+(i+1),value:filter.taxonomy||'',onChange:function(v){tax(i,'taxonomy',v);}}),el(TextControl,{label:__('Term slugs','cresco-canvas'),value:filter.terms||'',onChange:function(v){tax(i,'terms',v);},help:__('Comma separated.','cresco-canvas')}),el(SelectControl,{label:__('Operator','cresco-canvas'),value:filter.operator||'IN',options:[{label:'IN',value:'IN'},{label:'NOT IN',value:'NOT IN'}],onChange:function(v){tax(i,'operator',v);}}));})),
   el(PanelBody,{title:__('Layout and pagination','cresco-canvas'),initialOpen:false},
    el(RangeControl,{label:__('Columns','cresco-canvas'),value:a.columns,min:1,max:6,onChange:function(v){set({columns:v});}}),
    el(ToggleControl,{label:__('Enable pagination','cresco-canvas'),checked:!!a.pagination,onChange:function(v){set({pagination:v});}}),
    a.pagination&&el(TextControl,{label:__('Page parameter','cresco-canvas'),value:a.pageParam,onChange:function(v){set({pageParam:v});}}),
    el(TextControl,{label:__('Empty message','cresco-canvas'),value:a.emptyMessage,onChange:function(v){set({emptyMessage:v});}})
   )
  ),
  el('div',useBlockProps({className:'cresco-advanced-loop is-editor-preview'}),el(Notice,{status:'info',isDismissible:false},__('The advanced query is normalized and executed on the server.','cresco-canvas')),el(InnerBlocks,{allowedBlocks:contentBlocks,template:[['core/group',{},[['cresco/dynamic-image',{source:'featured',size:'medium',linkTo:'post'}],['cresco/dynamic-field',{source:'post',field:'title',tagName:'h3',linkTo:'post'}],['cresco/dynamic-field',{source:'post',field:'excerpt',tagName:'p'}]]]],templateLock:false}))
 );},save:function(){return el(InnerBlocks.Content);}
});
})(window.wp);
