(function(wp){
'use strict';
if(!wp||!wp.blocks||!wp.blockEditor||!wp.components||!wp.element||!wp.i18n){return;}
var el=wp.element.createElement;
var Fragment=wp.element.Fragment;
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

function numberValue(value){var parsed=parseInt(value,10);return isNaN(parsed)?0:parsed;}
function commaList(value){return String(value||'').split(',').map(function(item){return item.trim().toLowerCase().replace(/[^a-z0-9_-]/g,'');}).filter(Boolean).slice(0,3);}

registerBlockType('cresco/filterable-loop',{
 apiVersion:3,
 title:__('Filterable Loop','cresco-canvas'),
 icon:'filter',
 category:'cresco-canvas',
 description:__('Server-rendered Loop with signed AJAX filtering, facets, load-more, infinite scroll, URL sync, and WooCommerce presets.','cresco-canvas'),
 attributes:{
  postType:{type:'string',default:'post'},postsPerPage:{type:'number',default:6},order:{type:'string',default:'DESC'},orderby:{type:'string',default:'date'},
  authorId:{type:'number',default:0},parentId:{type:'number',default:0},search:{type:'string',default:''},dateAfter:{type:'string',default:''},dateBefore:{type:'string',default:''},
  includeIds:{type:'string',default:''},excludeIds:{type:'string',default:''},metaKey:{type:'string',default:''},metaValue:{type:'string',default:''},metaCompare:{type:'string',default:'='},metaType:{type:'string',default:'CHAR'},taxFilters:{type:'array',default:[]},
  columns:{type:'number',default:3},interactionMode:{type:'string',default:'ajax'},syncUrl:{type:'boolean',default:true},instanceId:{type:'string',default:''},searchFilter:{type:'boolean',default:true},facetTaxonomies:{type:'array',default:[]},wooPreset:{type:'string',default:'none'},emptyMessage:{type:'string',default:''},loadMoreLabel:{type:'string',default:''}
 },
 supports:{html:false,align:['wide','full'],spacing:true},
 edit:function(props){
  var a=props.attributes,set=props.setAttributes;
  var blockProps=useBlockProps({className:'cresco-filterable-loop is-editor-preview',style:{'--cresco-filterable-columns':a.columns}});
  return el(Fragment,null,
   el(InspectorControls,null,
    el(PanelBody,{title:__('Base query','cresco-canvas'),initialOpen:true},
     el(TextControl,{label:__('Post type slug','cresco-canvas'),value:a.postType,onChange:function(v){set({postType:v});}}),
     el(RangeControl,{label:__('Items per page','cresco-canvas'),value:a.postsPerPage,min:1,max:24,onChange:function(v){set({postsPerPage:v});}}),
     el(SelectControl,{label:__('Order by','cresco-canvas'),value:a.orderby,options:['date','modified','title','menu_order','rand','meta_value','meta_value_num'].map(function(v){return{label:v,value:v};}),onChange:function(v){set({orderby:v});}}),
     el(SelectControl,{label:__('Order','cresco-canvas'),value:a.order,options:[{label:'DESC',value:'DESC'},{label:'ASC',value:'ASC'}],onChange:function(v){set({order:v});}}),
     el(RangeControl,{label:__('Columns','cresco-canvas'),value:a.columns,min:1,max:6,onChange:function(v){set({columns:v});}}),
     el(TextControl,{label:__('Static search','cresco-canvas'),value:a.search,onChange:function(v){set({search:v});}}),
     el(TextControl,{label:__('Author ID','cresco-canvas'),type:'number',value:a.authorId||'',onChange:function(v){set({authorId:numberValue(v)});}}),
     el(TextControl,{label:__('Parent post ID','cresco-canvas'),type:'number',value:a.parentId||'',onChange:function(v){set({parentId:numberValue(v)});}}),
     el(TextControl,{label:__('Date after (YYYY-MM-DD)','cresco-canvas'),value:a.dateAfter,onChange:function(v){set({dateAfter:v});}}),
     el(TextControl,{label:__('Date before (YYYY-MM-DD)','cresco-canvas'),value:a.dateBefore,onChange:function(v){set({dateBefore:v});}}),
     el(TextControl,{label:__('Include post IDs','cresco-canvas'),help:__('Comma-separated, maximum 24.','cresco-canvas'),value:a.includeIds,onChange:function(v){set({includeIds:v});}}),
     el(TextControl,{label:__('Exclude post IDs','cresco-canvas'),help:__('Comma-separated, maximum 24.','cresco-canvas'),value:a.excludeIds,onChange:function(v){set({excludeIds:v});}})
    ),
    el(PanelBody,{title:__('Meta query','cresco-canvas'),initialOpen:false},
     el(TextControl,{label:__('Meta key','cresco-canvas'),value:a.metaKey,onChange:function(v){set({metaKey:v});}}),
     el(TextControl,{label:__('Meta value','cresco-canvas'),value:a.metaValue,onChange:function(v){set({metaValue:v});}}),
     el(SelectControl,{label:__('Compare','cresco-canvas'),value:a.metaCompare,options:['=','!=','>','>=','<','<=','LIKE','NOT LIKE','EXISTS','NOT EXISTS'].map(function(v){return{label:v,value:v};}),onChange:function(v){set({metaCompare:v});}}),
     el(SelectControl,{label:__('Data type','cresco-canvas'),value:a.metaType,options:['CHAR','NUMERIC','DATE'].map(function(v){return{label:v,value:v};}),onChange:function(v){set({metaType:v});}})
    ),
    el(PanelBody,{title:__('Interactive filters','cresco-canvas'),initialOpen:true},
     el(SelectControl,{label:__('Interaction mode','cresco-canvas'),value:a.interactionMode,options:[{label:__('AJAX pagination','cresco-canvas'),value:'ajax'},{label:__('Load more','cresco-canvas'),value:'load_more'},{label:__('Infinite scroll','cresco-canvas'),value:'infinite'}],onChange:function(v){set({interactionMode:v});}}),
     el(ToggleControl,{label:__('Synchronize filters with URL','cresco-canvas'),checked:!!a.syncUrl,onChange:function(v){set({syncUrl:v});}}),
     el(TextControl,{label:__('Instance ID','cresco-canvas'),help:__('Use a unique value when multiple filterable loops share a page.','cresco-canvas'),value:a.instanceId,onChange:function(v){set({instanceId:v});}}),
     el(ToggleControl,{label:__('Show search filter','cresco-canvas'),checked:!!a.searchFilter,onChange:function(v){set({searchFilter:v});}}),
     el(TextControl,{label:__('Facet taxonomy slugs','cresco-canvas'),help:__('Comma-separated, maximum three.','cresco-canvas'),value:(a.facetTaxonomies||[]).join(', '),onChange:function(v){set({facetTaxonomies:commaList(v)});}}),
     el(SelectControl,{label:__('WooCommerce preset','cresco-canvas'),value:a.wooPreset,options:[{label:__('None','cresco-canvas'),value:'none'},{label:__('Newest products','cresco-canvas'),value:'newest'},{label:__('Featured products','cresco-canvas'),value:'featured'},{label:__('On-sale products','cresco-canvas'),value:'sale'},{label:__('In-stock products','cresco-canvas'),value:'in_stock'},{label:__('Best selling','cresco-canvas'),value:'best_selling'},{label:__('Top rated','cresco-canvas'),value:'top_rated'}],onChange:function(v){set({wooPreset:v});}}),
     el(TextControl,{label:__('Load more label','cresco-canvas'),value:a.loadMoreLabel,onChange:function(v){set({loadMoreLabel:v});}}),
     el(TextControl,{label:__('Empty message','cresco-canvas'),value:a.emptyMessage,onChange:function(v){set({emptyMessage:v});}})
    )
   ),
   el('div',blockProps,
    el(Notice,{status:'info',isDismissible:false},__('The server signs the saved query and progressively enhances this Loop on the frontend. JavaScript-disabled visitors keep normal GET filtering and pagination.','cresco-canvas')),
    el('div',{className:'cresco-filterable-loop__editor-summary'},
     el('strong',null,__('Filterable Loop','cresco-canvas')),
     el('span',null,(a.postType||'post')+' · '+a.postsPerPage+' '+__('items','cresco-canvas')+' · '+a.interactionMode),
     (a.facetTaxonomies||[]).length?el('small',null,__('Facets: ','cresco-canvas')+a.facetTaxonomies.join(', ')):null
    ),
    el(InnerBlocks,{template:[['core/group',{},[['cresco/dynamic-image',{source:'featured',size:'large',linkTo:'post'}],['cresco/dynamic-field',{source:'post',field:'title',tagName:'h3',linkTo:'post'}],['cresco/dynamic-field',{source:'post',field:'excerpt',tagName:'p'}]]]],templateLock:false,allowedBlocks:['core/group','core/columns','core/column','core/paragraph','core/heading','core/buttons','core/button','core/image','core/separator','core/spacer','cresco/container','cresco/dynamic-field','cresco/dynamic-image','cresco/dynamic-gallery']})
   )
  );
 },
 save:function(){return el(InnerBlocks.Content);}
});
})(window.wp);
