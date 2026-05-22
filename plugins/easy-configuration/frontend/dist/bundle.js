(function(p,X,We,z){"use strict";var ue={exports:{}},ce={};/**
 * @license React
 * react-jsx-runtime.production.js
 *
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */var Ne;function Ue(){if(Ne)return ce;Ne=1;var r=Symbol.for("react.transitional.element"),t=Symbol.for("react.fragment");function n(s,a,l){var i=null;if(l!==void 0&&(i=""+l),a.key!==void 0&&(i=""+a.key),"key"in a){l={};for(var o in a)o!=="key"&&(l[o]=a[o])}else l=a;return a=l.ref,{$$typeof:r,type:s,key:i,ref:a!==void 0?a:null,props:l}}return ce.Fragment=t,ce.jsx=n,ce.jsxs=n,ce}var ie={};/**
 * @license React
 * react-jsx-runtime.development.js
 *
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */var Ce;function Be(){return Ce||(Ce=1,process.env.NODE_ENV!=="production"&&(function(){function r(c){if(c==null)return null;if(typeof c=="function")return c.$$typeof===de?null:c.displayName||c.name||null;if(typeof c=="string")return c;switch(c){case P:return"Fragment";case Y:return"Profiler";case L:return"StrictMode";case B:return"Suspense";case G:return"SuspenseList";case q:return"Activity"}if(typeof c=="object")switch(typeof c.tag=="number"&&console.error("Received an unexpected object in getComponentNameFromType(). This is likely a bug in React. Please file an issue."),c.$$typeof){case x:return"Portal";case ae:return c.displayName||"Context";case Q:return(c._context.displayName||"Context")+".Consumer";case U:var v=c.render;return c=c.displayName,c||(c=v.displayName||v.name||"",c=c!==""?"ForwardRef("+c+")":"ForwardRef"),c;case Z:return v=c.displayName||null,v!==null?v:r(c.type)||"Memo";case J:v=c._payload,c=c._init;try{return r(c(v))}catch{}}return null}function t(c){return""+c}function n(c){try{t(c);var v=!1}catch{v=!0}if(v){v=console;var k=v.error,C=typeof Symbol=="function"&&Symbol.toStringTag&&c[Symbol.toStringTag]||c.constructor.name||"Object";return k.call(v,"The provided key is an unsupported type %s. This value must be coerced to a string before using it here.",C),t(c)}}function s(c){if(c===P)return"<>";if(typeof c=="object"&&c!==null&&c.$$typeof===J)return"<...>";try{var v=r(c);return v?"<"+v+">":"<...>"}catch{return"<...>"}}function a(){var c=W.A;return c===null?null:c.getOwner()}function l(){return Error("react-stack-top-frame")}function i(c){if(w.call(c,"key")){var v=Object.getOwnPropertyDescriptor(c,"key").get;if(v&&v.isReactWarning)return!1}return c.key!==void 0}function o(c,v){function k(){y||(y=!0,console.error("%s: `key` is not a prop. Trying to access it will result in `undefined` being returned. If you need to access the same value within the child component, you should pass it as a different prop. (https://react.dev/link/special-props)",v))}k.isReactWarning=!0,Object.defineProperty(c,"key",{get:k,configurable:!0})}function u(){var c=r(this.type);return N[c]||(N[c]=!0,console.error("Accessing element.ref was removed in React 19. ref is now a regular prop. It will be removed from the JSX Element type in a future release.")),c=this.props.ref,c!==void 0?c:null}function d(c,v,k,C,he,ke){var E=k.ref;return c={$$typeof:g,type:c,key:v,props:k,_owner:C},(E!==void 0?E:null)!==null?Object.defineProperty(c,"ref",{enumerable:!1,get:u}):Object.defineProperty(c,"ref",{enumerable:!1,value:null}),c._store={},Object.defineProperty(c._store,"validated",{configurable:!1,enumerable:!1,writable:!0,value:0}),Object.defineProperty(c,"_debugInfo",{configurable:!1,enumerable:!1,writable:!0,value:null}),Object.defineProperty(c,"_debugStack",{configurable:!1,enumerable:!1,writable:!0,value:he}),Object.defineProperty(c,"_debugTask",{configurable:!1,enumerable:!1,writable:!0,value:ke}),Object.freeze&&(Object.freeze(c.props),Object.freeze(c)),c}function m(c,v,k,C,he,ke){var E=v.children;if(E!==void 0)if(C)if(K(E)){for(C=0;C<E.length;C++)h(E[C]);Object.freeze&&Object.freeze(E)}else console.error("React.jsx: Static children should always be an array. You are likely explicitly calling React.jsxs or React.jsxDEV. Use the Babel transform instead.");else h(E);if(w.call(v,"key")){E=r(c);var oe=Object.keys(v).filter(function(ht){return ht!=="key"});C=0<oe.length?"{key: someKey, "+oe.join(": ..., ")+": ...}":"{key: someKey}",ee[E+C]||(oe=0<oe.length?"{"+oe.join(": ..., ")+": ...}":"{}",console.error(`A props object containing a "key" prop is being spread into JSX:
  let props = %s;
  <%s {...props} />
React keys must be passed directly to JSX without using spread:
  let props = %s;
  <%s key={someKey} {...props} />`,C,E,oe,E),ee[E+C]=!0)}if(E=null,k!==void 0&&(n(k),E=""+k),i(v)&&(n(v.key),E=""+v.key),"key"in v){k={};for(var _e in v)_e!=="key"&&(k[_e]=v[_e])}else k=v;return E&&o(k,typeof c=="function"?c.displayName||c.name||"Unknown":c),d(c,E,k,a(),he,ke)}function h(c){j(c)?c._store&&(c._store.validated=1):typeof c=="object"&&c!==null&&c.$$typeof===J&&(c._payload.status==="fulfilled"?j(c._payload.value)&&c._payload.value._store&&(c._payload.value._store.validated=1):c._store&&(c._store.validated=1))}function j(c){return typeof c=="object"&&c!==null&&c.$$typeof===g}var b=p,g=Symbol.for("react.transitional.element"),x=Symbol.for("react.portal"),P=Symbol.for("react.fragment"),L=Symbol.for("react.strict_mode"),Y=Symbol.for("react.profiler"),Q=Symbol.for("react.consumer"),ae=Symbol.for("react.context"),U=Symbol.for("react.forward_ref"),B=Symbol.for("react.suspense"),G=Symbol.for("react.suspense_list"),Z=Symbol.for("react.memo"),J=Symbol.for("react.lazy"),q=Symbol.for("react.activity"),de=Symbol.for("react.client.reference"),W=b.__CLIENT_INTERNALS_DO_NOT_USE_OR_WARN_USERS_THEY_CANNOT_UPGRADE,w=Object.prototype.hasOwnProperty,K=Array.isArray,f=console.createTask?console.createTask:function(){return null};b={react_stack_bottom_frame:function(c){return c()}};var y,N={},A=b.react_stack_bottom_frame.bind(b,l)(),I=f(s(l)),ee={};ie.Fragment=P,ie.jsx=function(c,v,k){var C=1e4>W.recentlyCreatedOwnerStacks++;return m(c,v,k,!1,C?Error("react-stack-top-frame"):A,C?f(s(c)):I)},ie.jsxs=function(c,v,k){var C=1e4>W.recentlyCreatedOwnerStacks++;return m(c,v,k,!0,C?Error("react-stack-top-frame"):A,C?f(s(c)):I)}})()),ie}var Ee;function Ge(){return Ee||(Ee=1,process.env.NODE_ENV==="production"?ue.exports=Ue():ue.exports=Be()),ue.exports}var e=Ge();function Se(r){var t,n,s="";if(typeof r=="string"||typeof r=="number")s+=r;else if(typeof r=="object")if(Array.isArray(r)){var a=r.length;for(t=0;t<a;t++)r[t]&&(n=Se(r[t]))&&(s&&(s+=" "),s+=n)}else for(n in r)r[n]&&(s&&(s+=" "),s+=n);return s}function S(){for(var r,t,n=0,s="",a=arguments.length;n<a;n++)(r=arguments[n])&&(t=Se(r))&&(s&&(s+=" "),s+=t);return s}/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Te=(...r)=>r.filter((t,n,s)=>!!t&&t.trim()!==""&&s.indexOf(t)===n).join(" ").trim();/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Xe=r=>r.replace(/([a-z0-9])([A-Z])/g,"$1-$2").toLowerCase();/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Qe=r=>r.replace(/^([A-Z])|[\s-_]+(\w)/g,(t,n,s)=>s?s.toUpperCase():n.toLowerCase());/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Re=r=>{const t=Qe(r);return t.charAt(0).toUpperCase()+t.slice(1)};/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */var ge={xmlns:"http://www.w3.org/2000/svg",width:24,height:24,viewBox:"0 0 24 24",fill:"none",stroke:"currentColor",strokeWidth:2,strokeLinecap:"round",strokeLinejoin:"round"};/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Ze=r=>{for(const t in r)if(t.startsWith("aria-")||t==="role"||t==="title")return!0;return!1},qe=p.createContext({}),er=()=>p.useContext(qe),rr=p.forwardRef(({color:r,size:t,strokeWidth:n,absoluteStrokeWidth:s,className:a="",children:l,iconNode:i,...o},u)=>{const{size:d=24,strokeWidth:m=2,absoluteStrokeWidth:h=!1,color:j="currentColor",className:b=""}=er()??{},g=s??h?Number(n??m)*24/Number(t??d):n??m;return p.createElement("svg",{ref:u,...ge,width:t??d??ge.width,height:t??d??ge.height,stroke:r??j,strokeWidth:g,className:Te("lucide",b,a),...!l&&!Ze(o)&&{"aria-hidden":"true"},...o},[...i.map(([x,P])=>p.createElement(x,P)),...Array.isArray(l)?l:[l]])});/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const _=(r,t)=>{const n=p.forwardRef(({className:s,...a},l)=>p.createElement(rr,{ref:l,iconNode:t,className:Te(`lucide-${Xe(Re(r))}`,`lucide-${r}`,s),...a}));return n.displayName=Re(r),n};/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const tr=_("arrow-left",[["path",{d:"m12 19-7-7 7-7",key:"1l729n"}],["path",{d:"M19 12H5",key:"x3x0zl"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const me=_("check",[["path",{d:"M20 6 9 17l-5-5",key:"1gmf2c"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const nr=_("chevron-down",[["path",{d:"m6 9 6 6 6-6",key:"qrunsl"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const sr=_("circle-check",[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}],["path",{d:"m9 12 2 2 4-4",key:"dzmm74"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ar=_("circle-question-mark",[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}],["path",{d:"M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3",key:"1u773s"}],["path",{d:"M12 17h.01",key:"p32p05"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const or=_("circle-x",[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}],["path",{d:"m15 9-6 6",key:"1uzhvr"}],["path",{d:"m9 9 6 6",key:"z0biqf"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const cr=_("copy",[["rect",{width:"14",height:"14",x:"8",y:"8",rx:"2",ry:"2",key:"17jyea"}],["path",{d:"M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2",key:"zix9uf"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ir=_("download",[["path",{d:"M12 15V3",key:"m9g1x1"}],["path",{d:"M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4",key:"ih7n3h"}],["path",{d:"m7 10 5 5 5-5",key:"brsn70"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const lr=_("file-plus-corner",[["path",{d:"M11.35 22H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.706.706l3.588 3.588A2.4 2.4 0 0 1 20 8v5.35",key:"17jvcc"}],["path",{d:"M14 2v5a1 1 0 0 0 1 1h5",key:"wfsgrz"}],["path",{d:"M14 19h6",key:"bvotb8"}],["path",{d:"M17 16v6",key:"18yu1i"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const dr=_("info",[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}],["path",{d:"M12 16v-4",key:"1dtifu"}],["path",{d:"M12 8h.01",key:"e9boi3"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ur=_("pencil",[["path",{d:"M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z",key:"1a8usu"}],["path",{d:"m15 5 4 4",key:"1mk7zo"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const mr=_("power",[["path",{d:"M12 2v10",key:"mnfbl"}],["path",{d:"M18.4 6.6a9 9 0 1 1-12.77.04",key:"obofu9"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const pr=_("rotate-ccw",[["path",{d:"M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8",key:"1357e3"}],["path",{d:"M3 3v5h5",key:"1xhq8a"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ze=_("save",[["path",{d:"M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z",key:"1c8476"}],["path",{d:"M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7",key:"1ydtos"}],["path",{d:"M7 3v4a1 1 0 0 0 1 1h7",key:"t51u73"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const xe=_("search",[["path",{d:"m21 21-4.34-4.34",key:"14j7rj"}],["circle",{cx:"11",cy:"11",r:"8",key:"4ej97u"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const fr=_("sliders-horizontal",[["path",{d:"M10 5H3",key:"1qgfaw"}],["path",{d:"M12 19H3",key:"yhmn1j"}],["path",{d:"M14 3v4",key:"1sua03"}],["path",{d:"M16 17v4",key:"1q0r14"}],["path",{d:"M21 12h-9",key:"1o4lsq"}],["path",{d:"M21 19h-5",key:"1rlt1p"}],["path",{d:"M21 5h-7",key:"1oszz2"}],["path",{d:"M8 10v4",key:"tgpxqk"}],["path",{d:"M8 12H3",key:"a7s4jb"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const hr=_("trash-2",[["path",{d:"M10 11v6",key:"nco0om"}],["path",{d:"M14 11v6",key:"outv1u"}],["path",{d:"M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6",key:"miytrc"}],["path",{d:"M3 6h18",key:"d0wm0j"}],["path",{d:"M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2",key:"e791ji"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Pe=_("triangle-alert",[["path",{d:"m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3",key:"wmoenq"}],["path",{d:"M12 9v4",key:"juzpu7"}],["path",{d:"M12 17h.01",key:"p32p05"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const gr=_("upload",[["path",{d:"M12 3v12",key:"1x0j5s"}],["path",{d:"m17 8-5-5-5 5",key:"7q97r8"}],["path",{d:"M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4",key:"ih7n3h"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const xr=_("x",[["path",{d:"M18 6 6 18",key:"1bl5f8"}],["path",{d:"m6 6 12 12",key:"d8bk6v"}]]),Ae=p.createContext(null);function pe(){const r=p.useContext(Ae);if(r===null)throw new Error("useToast must be used within a ToastProvider");return r}function Oe({children:r}){const[t,n]=p.useState([]),s=p.useRef(0),a=p.useCallback(o=>{n(u=>u.filter(d=>d.id!==o))},[]),l=p.useCallback((o,u="info")=>{s.current+=1;const d=s.current;n(m=>[...m,{id:d,variant:u,message:o}]),window.setTimeout(()=>a(d),4500)},[a]),i=p.useMemo(()=>({show:l,success:o=>l(o,"success"),error:o=>l(o,"error"),warning:o=>l(o,"warning")}),[l]);return e.jsxs(Ae.Provider,{value:i,children:[r,t.length>0&&e.jsx("div",{className:"ec-toast-host",children:t.map(o=>e.jsxs("div",{className:S("ec-toast",`ec-toast-${o.variant}`),role:"status",onClick:()=>a(o.id),children:[e.jsx("span",{className:"ec-toast-icon",children:e.jsx(vr,{variant:o.variant})}),e.jsx("span",{children:o.message})]},o.id))})]})}function vr({variant:r}){return r==="success"?e.jsx(sr,{size:16}):r==="error"?e.jsx(or,{size:16}):r==="warning"?e.jsx(Pe,{size:16}):e.jsx(dr,{size:16})}const Me=window.__PEREGRINE_PLUGINS__,re="easy-configuration",O=`/api/plugins/${re}`;function br(){var r;return((r=document.querySelector('meta[name="csrf-token"]'))==null?void 0:r.getAttribute("content"))??""}async function M(r,t={}){const n=await fetch(r,{...t,credentials:"same-origin",headers:{"Content-Type":"application/json",Accept:"application/json","X-CSRF-TOKEN":br(),...t.headers}});if(!n.ok){const a=(await n.json().catch(()=>({}))).error;throw{status:n.status,code:a==null?void 0:a.code,message:a==null?void 0:a.message,messages:a==null?void 0:a.messages,fields:a==null?void 0:a.fields}}if(n.status!==204)return await n.json()}function T(){const{t:r,i18n:t}=We.useTranslation(re);return{t:r,lang:(t.language??"en").slice(0,2)}}function $(r,t,n=""){return r?r[t]??r.en??r.fr??Object.values(r)[0]??n:n}function te({size:r}){return e.jsx("span",{className:S("ec-spinner",r==="lg"&&"ec-spinner-lg"),"aria-hidden":!0})}function D({children:r,className:t,hover:n}){return e.jsx("div",{className:S("ec-card",n&&"ec-card-hover",t),children:r})}function V({variant:r="muted",children:t}){return e.jsx("span",{className:S("ec-badge",`ec-badge-${r}`),children:t})}function le({children:r}){return e.jsx("div",{className:"ec-empty",children:r})}function yr({tabs:r,active:t,onChange:n}){return e.jsx("div",{className:"ec-tabs",role:"tablist",children:r.map(s=>e.jsx("button",{type:"button",role:"tab","aria-selected":s.id===t,className:S("ec-tab",s.id===t&&"ec-tab-active"),onClick:()=>n(s.id),children:s.label},s.id))})}function jr({content:r,children:t}){return e.jsxs("span",{className:"ec-tooltip",tabIndex:0,children:[t,e.jsx("span",{role:"tooltip",className:"ec-tooltip-pop",children:r})]})}function R({variant:r="primary",size:t="md",loading:n=!1,disabled:s,type:a="button",className:l,children:i,...o}){return e.jsxs("button",{...o,type:a,disabled:s||n,className:S("ec-btn",`ec-btn-${r}`,t==="sm"&&"ec-btn-sm",l),children:[n&&e.jsx("span",{className:"ec-spinner","aria-hidden":!0}),i]})}function ve({label:r,type:t="button",className:n,children:s,...a}){return e.jsx("button",{...a,type:t,"aria-label":r,title:r,className:S("ec-btn","ec-btn-icon",n),children:s})}function F({invalid:r,className:t,...n}){return e.jsx("input",{...n,className:S("ec-input",r&&"ec-input-invalid",t)})}function be({invalid:r,className:t,...n}){return e.jsx("textarea",{...n,className:S("ec-textarea",r&&"ec-input-invalid",t)})}function wr({value:r,onChange:t,children:n,className:s,disabled:a,invalid:l}){return e.jsx("select",{className:S("ec-select",l&&"ec-input-invalid",s),value:r,disabled:a,onChange:i=>t(i.target.value),children:n})}function Fe({checked:r,onChange:t,disabled:n,label:s}){return e.jsx("button",{type:"button",role:"switch","aria-checked":r,"aria-label":s,disabled:n,className:S("ec-toggle",r&&"ec-toggle-on"),onClick:()=>t(!r),children:e.jsx("span",{className:"ec-toggle-knob"})})}const fe=["ec-admin-templates"];function kr(){return z.useQuery({queryKey:fe,queryFn:()=>M(`${O}/admin/templates`).then(r=>r.data)})}function _r(r){return z.useQuery({queryKey:["ec-admin-template",r],enabled:r!==null,queryFn:()=>M(`${O}/admin/templates/${r??""}`).then(t=>t.data)})}function Nr(){return z.useQuery({queryKey:["ec-admin-eggs"],staleTime:5*6e4,queryFn:()=>M(`${O}/admin/eggs`).then(r=>r.data)})}function Cr(){const r=z.useQueryClient();return z.useMutation({mutationFn:({id:t,template:n})=>t!==null?M(`${O}/admin/templates/${t}`,{method:"PUT",body:JSON.stringify({template:n})}):M(`${O}/admin/templates`,{method:"POST",body:JSON.stringify({template:n})}),onSuccess:()=>r.invalidateQueries({queryKey:fe})})}function Er(){const r=z.useQueryClient();return z.useMutation({mutationFn:t=>M(`${O}/admin/templates/import`,{method:"POST",body:JSON.stringify({content:t})}),onSuccess:()=>r.invalidateQueries({queryKey:fe})})}function Sr(){const r=z.useQueryClient();return z.useMutation({mutationFn:t=>M(`${O}/admin/templates/${t}`,{method:"DELETE"}),onSuccess:()=>r.invalidateQueries({queryKey:fe})})}function Tr({value:r,onChange:t,eggs:n,loading:s}){const{t:a}=T(),[l,i]=p.useState(""),o=n.filter(d=>d.name.toLowerCase().includes(l.trim().toLowerCase())),u=d=>{t(r.includes(d)?r.filter(m=>m!==d):[...r,d])};return e.jsxs("div",{className:"ec-field-group",children:[e.jsx("label",{children:a("admin.editor.target_eggs")}),e.jsxs("div",{className:"ec-search",children:[e.jsx("span",{className:"ec-search-icon",children:e.jsx(xe,{size:14})}),e.jsx(F,{value:l,placeholder:a("admin.editor.search_eggs"),onChange:d=>i(d.target.value)})]}),s?e.jsxs("div",{className:"ec-row ec-muted",children:[e.jsx(te,{})," ",a("common.loading")]}):e.jsxs("div",{className:"ec-egg-list",children:[o.map(d=>{const m=r.includes(d.id);return e.jsxs("button",{type:"button",className:S("ec-server-row",m&&"ec-server-row-on"),onClick:()=>u(d.id),children:[d.banner_image?e.jsx("img",{className:"ec-server-thumb",src:d.banner_image,alt:""}):e.jsx("span",{className:"ec-server-thumb"}),e.jsx("span",{className:"ec-grow ec-truncate",children:d.name}),e.jsxs("span",{className:"ec-muted",children:["#",d.id]}),m&&e.jsx(me,{size:16})]},d.id)}),o.length===0&&e.jsx("div",{className:"ec-empty",children:a("admin.editor.no_eggs")})]})]})}function Rr({param:r,value:t,onChange:n,disabled:s,invalid:a}){const{lang:l}=T(),i=r.config;switch(r.display_type){case"boolean":return e.jsx(zr,{config:i,value:t,onChange:n,disabled:s});case"slider":return e.jsx(Pr,{config:i,value:t,onChange:n,disabled:s,invalid:a});case"number":return e.jsx(F,{className:"ec-input-narrow",type:"number",min:i.min,max:i.max,step:i.step??(i.float?"any":1),value:t,disabled:s,invalid:a,onChange:o=>n(o.target.value)});case"select":return e.jsx(wr,{value:t,disabled:s,invalid:a,onChange:n,children:(i.options??[]).map(o=>e.jsx("option",{value:o.value,children:$(o.label,l,o.value)},o.value))});case"multiselect":return e.jsx(Ar,{config:i,value:t,onChange:n,disabled:s});case"textarea":return e.jsx(be,{value:t,disabled:s,invalid:a,maxLength:i.max_length,onChange:o=>n(o.target.value)});case"color":return e.jsx(Or,{value:t,onChange:n,disabled:s,invalid:a});default:return e.jsx(F,{value:t,disabled:s,invalid:a,maxLength:i.max_length,onChange:o=>n(o.target.value)})}}function zr({config:r,value:t,onChange:n,disabled:s}){const a=r.true_value??"true",l=r.false_value??"false";return e.jsx(Fe,{checked:t===a,disabled:s,onChange:i=>n(i?a:l)})}function Pr({config:r,value:t,onChange:n,disabled:s,invalid:a}){const l=r.min??0,i=r.max??100,o=r.step??1;return e.jsxs("div",{className:"ec-slider-wrap",children:[e.jsx("input",{type:"range",className:"ec-slider",min:l,max:i,step:o,value:t,disabled:s,onChange:u=>n(u.target.value)}),e.jsx(F,{className:"ec-slider-number",type:"number",min:l,max:i,step:o,value:t,disabled:s,invalid:a,onChange:u=>n(u.target.value)})]})}function Ar({config:r,value:t,onChange:n,disabled:s}){const{lang:a}=T(),l=r.separator&&r.separator!==""?r.separator:",",i=t.split(l).map(u=>u.trim()).filter(u=>u!==""),o=u=>{const d=i.includes(u)?i.filter(m=>m!==u):[...i,u];n(d.join(l))};return e.jsx("div",{className:"ec-chips",children:(r.options??[]).map(u=>e.jsx("button",{type:"button",disabled:s,className:S("ec-chip",i.includes(u.value)&&"ec-chip-on"),onClick:()=>o(u.value),children:$(u.label,a,u.value)},u.value))})}function Or({value:r,onChange:t,disabled:n,invalid:s}){const a=/^#[0-9a-fA-F]{6}$/.test(r)?r:`#${r}`,l=/^#[0-9a-fA-F]{6}$/.test(a)?a:"#000000";return e.jsxs("div",{className:"ec-color",children:[e.jsx("input",{type:"color",className:"ec-color-swatch",value:l,disabled:n,onChange:i=>t(i.target.value)}),e.jsx(F,{className:"ec-input-narrow",value:r,disabled:n,invalid:s,onChange:i=>t(i.target.value)})]})}function Le({param:r,value:t,onChange:n,disabled:s,dirty:a,saved:l,invalid:i,onReset:o,boost:u}){const{t:d,lang:m}=T(),h=$(r.label,m,r.key),j=$(r.description,m,""),b=r.config.default,g=o!==void 0&&b!==void 0&&!s&&t!==String(b);return e.jsxs("div",{className:S("ec-field",a&&"ec-field-dirty"),children:[e.jsxs("div",{className:"ec-field-label-col",children:[e.jsxs("span",{className:"ec-field-label",children:[h,j!==""&&e.jsx(jr,{content:j,children:e.jsx("span",{className:"ec-help",children:e.jsx(ar,{size:13})})}),r.inferred&&e.jsx(V,{variant:"muted",children:d("field.auto_detected")})]}),j!==""&&e.jsx("span",{className:"ec-field-desc ec-truncate",children:j}),e.jsx("span",{className:"ec-field-desc ec-muted",children:r.section?`${r.section} · ${r.key}`:r.key})]}),e.jsxs("div",{className:"ec-field-control",children:[u,e.jsx(Rr,{param:r,value:t,onChange:n,disabled:s,invalid:i}),l&&e.jsx("span",{className:"ec-field-saved","aria-hidden":!0,children:e.jsx(me,{size:15})}),g&&e.jsx(ve,{label:d("field.reset_default"),className:"ec-reset",onClick:o,children:e.jsx(pr,{size:14})})]})]})}function H(r){return typeof r=="object"&&r!==null&&!Array.isArray(r)}function Ie(r,t,n){const s=H(n.config)?n.config:{},a=s.default;return{key:r,section:t,display_type:typeof n.display_type=="string"?n.display_type:"text",config:s,label:H(n.label)?n.label:null,description:H(n.description)?n.description:null,value:a===void 0?"":String(a),inferred:!1}}function Mr(r){const t=[];if(!H(r))return{sectioned:!1,params:t};let n=!1;for(const[s,a]of Object.entries(r))if(H(a)&&"display_type"in a)t.push(Ie(s,null,a));else if(H(a)){n=!0;for(const[l,i]of Object.entries(a))H(i)&&t.push(Ie(l,s,i))}return{sectioned:n,params:t}}function Fr({files:r}){const{t,lang:n}=T(),[s,a]=p.useState({});return!Array.isArray(r)||r.length===0?e.jsx("div",{className:"ec-empty",children:t("admin.editor.preview_empty")}):e.jsx("div",{className:"ec-stack",children:r.map((l,i)=>{if(!H(l))return null;const{params:o}=Mr(l.parameters),u=H(l.label)?$(l.label,n,String(l.path??"")):String(l.path??`file ${i+1}`);return e.jsxs("div",{className:"ec-section-group",children:[e.jsx("div",{className:"ec-section-head",children:u}),e.jsxs("div",{className:"ec-section-body",children:[o.map(d=>{const m=`${i}:${d.section??""}:${d.key}`;return e.jsx(Le,{param:d,value:s[m]??d.value,onChange:h=>a(j=>({...j,[m]:h}))},m)}),o.length===0&&e.jsx("div",{className:"ec-empty",children:t("admin.editor.no_params")})]})]},i)})})}function $e(r,t){const n={};return r.trim()!==""&&(n.en=r.trim()),t.trim()!==""&&(n.fr=t.trim()),n}function De(r){try{return JSON.parse(r)}catch{return null}}function Ve({initial:r,isNew:t}){const{t:n}=T(),s=X.useNavigate(),a=pe(),l=Nr(),i=Cr(),[o,u]=p.useState(r),[d,m]=p.useState("edit"),[h,j]=p.useState([]),b=x=>u(P=>({...P,...x})),g=()=>{const x=De(o.filesJson);if(x===null){j([n("admin.editor.invalid_files_json")]);return}const P={id:o.id,version:o.version===""?"1.0.0":o.version,name:$e(o.nameEn,o.nameFr),description:$e(o.descEn,o.descFr),author:o.author===""?null:o.author,target_eggs:o.targetEggs,boost:{enabled:o.boostEnabled,parameter_blacklist:o.blacklist.split(",").map(L=>L.trim()).filter(L=>L!=="")},files:x};j([]),i.mutate({id:t?null:o.id,template:P},{onSuccess:()=>{a.success(n("admin.editor.saved")),s(`/plugins/${re}`)},onError:L=>{const Y=L;j(Y.messages??[Y.message??n("errors.generic")]),a.error(n("admin.editor.save_failed"))}})};return e.jsxs("div",{className:"ec-page",children:[e.jsxs("div",{className:"ec-between",children:[e.jsxs("div",{className:"ec-row",children:[e.jsxs(R,{variant:"ghost",onClick:()=>s(`/plugins/${re}`),children:[e.jsx(tr,{size:15})," ",n("common.back")]}),e.jsx("h1",{className:"ec-title",children:t?n("admin.editor.title_new"):o.id})]}),e.jsxs(R,{loading:i.isPending,onClick:g,children:[e.jsx(ze,{size:15})," ",n("common.save")]})]}),h.length>0&&e.jsx(D,{children:e.jsx("ul",{className:"ec-error-list",children:h.map(x=>e.jsx("li",{children:x},x))})}),e.jsx(yr,{active:d,onChange:x=>m(x),tabs:[{id:"edit",label:n("admin.editor.tab_edit")},{id:"preview",label:n("admin.editor.tab_preview")}]}),d==="edit"?e.jsxs("div",{className:"ec-stack",children:[e.jsx(D,{children:e.jsxs("div",{className:"ec-stack",children:[e.jsxs("div",{className:"ec-field-group",children:[e.jsx("label",{children:n("admin.editor.id")}),e.jsx(F,{value:o.id,disabled:!t,placeholder:"minecraft-vanilla",onChange:x=>b({id:x.target.value})})]}),e.jsxs("div",{className:"ec-cols-2",children:[e.jsxs("div",{className:"ec-field-group",children:[e.jsx("label",{children:n("admin.editor.name_en")}),e.jsx(F,{value:o.nameEn,onChange:x=>b({nameEn:x.target.value})})]}),e.jsxs("div",{className:"ec-field-group",children:[e.jsx("label",{children:n("admin.editor.name_fr")}),e.jsx(F,{value:o.nameFr,onChange:x=>b({nameFr:x.target.value})})]})]}),e.jsxs("div",{className:"ec-cols-2",children:[e.jsxs("div",{className:"ec-field-group",children:[e.jsx("label",{children:n("admin.editor.desc_en")}),e.jsx(F,{value:o.descEn,onChange:x=>b({descEn:x.target.value})})]}),e.jsxs("div",{className:"ec-field-group",children:[e.jsx("label",{children:n("admin.editor.desc_fr")}),e.jsx(F,{value:o.descFr,onChange:x=>b({descFr:x.target.value})})]})]}),e.jsxs("div",{className:"ec-field-group",children:[e.jsx("label",{children:n("admin.editor.author")}),e.jsx(F,{value:o.author,onChange:x=>b({author:x.target.value})})]})]})}),e.jsx(D,{children:e.jsx(Tr,{value:o.targetEggs,onChange:x=>b({targetEggs:x}),eggs:l.data??[],loading:l.isLoading})}),e.jsx(D,{children:e.jsxs("div",{className:"ec-stack",children:[e.jsxs("div",{className:"ec-between",children:[e.jsx("span",{className:"ec-field-label",children:n("admin.editor.boost_enabled")}),e.jsx(Fe,{checked:o.boostEnabled,onChange:x=>b({boostEnabled:x}),label:n("admin.editor.boost_enabled")})]}),o.boostEnabled&&e.jsxs("div",{className:"ec-field-group",children:[e.jsx("label",{children:n("admin.editor.boost_blacklist")}),e.jsx(F,{value:o.blacklist,placeholder:"server-port, rcon.port",onChange:x=>b({blacklist:x.target.value})}),e.jsx("span",{className:"ec-field-desc ec-muted",children:n("admin.editor.boost_blacklist_hint")})]})]})}),e.jsx(D,{children:e.jsxs("div",{className:"ec-field-group",children:[e.jsx("label",{children:n("admin.editor.files_json")}),e.jsx(be,{className:"ec-mono",value:o.filesJson,spellCheck:!1,onChange:x=>b({filesJson:x.target.value})})]})})]}):e.jsx(Fr,{files:De(o.filesJson)})]})}const Lr=`[
  {
    "id": "server-properties",
    "path": "server.properties",
    "format": "properties",
    "enabled": true,
    "label": { "en": "Server properties", "fr": "Propriétés serveur" },
    "parameters": {
      "max-players": {
        "display_type": "slider",
        "config": { "min": 1, "max": 100, "step": 1 },
        "label": { "en": "Max players", "fr": "Joueurs max" }
      }
    }
  }
]`;function ye(r){return typeof r=="object"&&r!==null&&!Array.isArray(r)}function ne(r,t=""){return typeof r=="string"?r:t}function Ye(){return{id:"",version:"1.0.0",nameEn:"",nameFr:"",descEn:"",descFr:"",author:"",targetEggs:[],boostEnabled:!1,blacklist:"",filesJson:Lr}}function Ir(r,t){if(t===null)return{...Ye(),id:r};const n=ye(t.name)?t.name:{},s=ye(t.description)?t.description:{},a=ye(t.boost)?t.boost:{},l=Array.isArray(a.parameter_blacklist)?a.parameter_blacklist.map(String):[],i=Array.isArray(t.target_eggs)?t.target_eggs.filter(o=>typeof o=="number"):[];return{id:r,version:ne(t.version,"1.0.0"),nameEn:ne(n.en),nameFr:ne(n.fr),descEn:ne(s.en),descFr:ne(s.fr),author:ne(t.author),targetEggs:i,boostEnabled:a.enabled===!0,blacklist:l.join(", "),filesJson:JSON.stringify(t.files??[],null,2)}}function Je(){var a;const{t:r}=T(),{templateId:t}=X.useParams(),n=t===void 0,s=_r(n?null:t??null);return n?e.jsx(Ve,{initial:Ye(),isNew:!0}):s.isError&&((a=s.error)==null?void 0:a.status)===403?e.jsx("div",{className:"ec-page",children:e.jsx(D,{children:e.jsx(le,{children:r("admin.unauthorized")})})}):s.isLoading||s.data===void 0?e.jsx("div",{className:"ec-page",children:e.jsxs("div",{className:"ec-row ec-muted",children:[e.jsx(te,{})," ",r("common.loading")]})}):e.jsx(Ve,{initial:Ir(s.data.id,s.data.definition),isNew:!1},s.data.id)}function Ke({open:r,onClose:t,title:n,children:s,footer:a,size:l="md",closeLabel:i}){return p.useEffect(()=>{if(!r)return;const o=u=>{u.key==="Escape"&&t()};return window.addEventListener("keydown",o),()=>window.removeEventListener("keydown",o)},[r,t]),r?e.jsx("div",{className:"ec-scrim",onMouseDown:o=>{o.target===o.currentTarget&&t()},children:e.jsxs("div",{className:S("ec-dialog",l==="lg"&&"ec-dialog-lg"),role:"dialog","aria-modal":"true",children:[e.jsxs("div",{className:"ec-dialog-head",children:[e.jsx("p",{className:"ec-dialog-title ec-grow",children:n}),e.jsx(ve,{label:i,onClick:t,children:e.jsx(xr,{size:16})})]}),s,a!==void 0&&e.jsx("div",{className:"ec-dialog-foot",children:a})]})}):null}function $r(){var b;const{t:r,lang:t}=T(),n=X.useNavigate(),s=pe(),a=kr(),l=Sr(),i=Er(),[o,u]=p.useState(!1),[d,m]=p.useState("");if(a.isError&&((b=a.error)==null?void 0:b.status)===403)return e.jsx("div",{className:"ec-page",children:e.jsx(D,{children:e.jsx(le,{children:r("admin.unauthorized")})})});const h=g=>{window.confirm(r("admin.list.confirm_delete",{id:g}))&&l.mutate(g,{onSuccess:()=>s.success(r("admin.list.deleted")),onError:()=>s.error(r("errors.generic"))})},j=()=>{i.mutate(d,{onSuccess:()=>{s.success(r("admin.list.imported")),u(!1),m("")},onError:g=>s.error(g.message??r("admin.list.import_failed"))})};return e.jsxs("div",{className:"ec-page",children:[e.jsxs("div",{className:"ec-between",children:[e.jsxs("div",{children:[e.jsx("h1",{className:"ec-title",children:r("admin.list.title")}),e.jsx("p",{className:"ec-subtitle",children:r("admin.list.subtitle")})]}),e.jsxs("div",{className:"ec-row",children:[e.jsxs(R,{variant:"secondary",onClick:()=>u(!0),children:[e.jsx(gr,{size:15})," ",r("admin.list.import")]}),e.jsxs(R,{onClick:()=>n(`/plugins/${re}/new`),children:[e.jsx(lr,{size:15})," ",r("admin.list.new")]})]})]}),a.isLoading?e.jsxs("div",{className:"ec-row ec-muted",children:[e.jsx(te,{})," ",r("common.loading")]}):a.data&&a.data.length>0?e.jsx("div",{className:"ec-grid",children:a.data.map(g=>e.jsxs(D,{hover:!0,className:"ec-template-card",children:[e.jsxs("div",{className:"ec-between",children:[e.jsx("strong",{className:"ec-truncate",children:$(g.name,t,g.template_id)}),g.is_valid?e.jsxs(V,{variant:"success",children:["v",g.version]}):e.jsx(V,{variant:"warning",children:r("admin.list.invalid")})]}),e.jsx("span",{className:"ec-subtitle ec-truncate",children:g.template_id}),!g.is_valid&&g.last_error!==null&&e.jsx("span",{className:"ec-field-desc ec-truncate",children:g.last_error}),e.jsxs("div",{className:"ec-template-card-foot",children:[e.jsx(V,{variant:"muted",children:r("admin.list.files",{count:g.file_count})}),e.jsx(V,{variant:"muted",children:r("admin.list.eggs",{count:g.target_eggs.length})}),g.boost_enabled&&e.jsx(V,{variant:"accent",children:r("admin.list.boost")})]}),e.jsxs("div",{className:"ec-row",children:[e.jsxs(R,{size:"sm",variant:"secondary",onClick:()=>n(`/plugins/${re}/${g.template_id}`),children:[e.jsx(ur,{size:13})," ",r("common.edit")]}),e.jsxs("a",{className:"ec-btn ec-btn-ghost ec-btn-sm",href:`${O}/admin/templates/${g.template_id}/export`,children:[e.jsx(ir,{size:13})," ",r("common.export")]}),e.jsx(ve,{label:r("common.delete"),onClick:()=>h(g.template_id),children:e.jsx(hr,{size:14})})]})]},g.template_id))}):e.jsx(D,{children:e.jsx(le,{children:r("admin.list.empty")})}),e.jsx(Ke,{open:o,onClose:()=>u(!1),closeLabel:r("common.close"),title:r("admin.list.import_title"),footer:e.jsxs(e.Fragment,{children:[e.jsx(R,{variant:"ghost",onClick:()=>u(!1),children:r("common.cancel")}),e.jsx(R,{loading:i.isPending,onClick:j,children:r("admin.list.import")})]}),children:e.jsx("div",{className:"ec-dialog-body",children:e.jsx(be,{className:"ec-mono",value:d,placeholder:'{ "id": "minecraft-vanilla", ... }',onChange:g=>m(g.target.value)})})})]})}function Dr(){return e.jsx(Oe,{children:e.jsx("div",{className:"ec-root",children:e.jsxs(X.Routes,{children:[e.jsx(X.Route,{path:"",element:e.jsx($r,{})}),e.jsx(X.Route,{path:"new",element:e.jsx(Je,{})}),e.jsx(X.Route,{path:":templateId",element:e.jsx(Je,{})})]})})})}const je="";function Vr(r,t,n){return`${r}${je}${t??""}${je}${n}`}function se(r,t){return Vr(r,t.section,t.key)}function Yr(r,t){return`${r}${je}${t}`}function Jr(r,t){const n=r.config;switch(r.display_type){case"number":case"slider":{if(t.trim()===""||!Number.isFinite(Number(t)))return"number";const s=Number(t);return n.min!==void 0&&s<n.min?"min":n.max!==void 0&&s>n.max?"max":!n.float&&t.includes(".")?"integer":null}case"select":{const s=(n.options??[]).map(a=>a.value);return s.length===0||s.includes(t)?null:"option"}case"multiselect":{const s=n.separator&&n.separator!==""?n.separator:",",a=(n.options??[]).map(l=>l.value);if(a.length===0)return null;for(const l of t.split(s).map(i=>i.trim()).filter(i=>i!==""))if(!a.includes(l))return"option";return null}case"boolean":{const s=n.true_value??"true",a=n.false_value??"false";return t===s||t===a?null:"boolean"}case"text":{if(n.max_length!==void 0&&t.length>n.max_length)return"length";if(n.regex!==void 0&&n.regex!=="")try{if(!new RegExp(n.regex).test(t))return"format"}catch{return null}return null}case"textarea":return n.max_length!==void 0&&t.length>n.max_length?"length":null;case"color":return/^#?[0-9a-fA-F]{6}$/.test(t)?null:"color";default:return null}}const we=(r,t)=>r[t]??!0;function Kr({templates:r,selected:t,setSelected:n}){const{t:s,lang:a}=T(),l=r.flatMap(d=>d.files),i=d=>{n(m=>({...m,[d]:!we(m,d)}))},o=d=>d.parameters.every(m=>we(t,se(d.id,m))),u=d=>{const m=!o(d);n(h=>{const j={...h};for(const b of d.parameters)j[se(d.id,b)]=m;return j})};return l.length===0?e.jsx("div",{className:"ec-empty",children:s("copy.no_params")}):e.jsx("div",{className:"ec-stack",children:l.map(d=>e.jsxs("div",{className:"ec-section-group",children:[e.jsxs("label",{className:"ec-section-head ec-check-row",children:[e.jsx("input",{type:"checkbox",checked:o(d),onChange:()=>u(d)}),e.jsx("span",{children:$(d.label,a,d.path)}),e.jsx("span",{className:"ec-section-count",children:d.parameters.length})]}),e.jsx("div",{className:"ec-section-body",children:d.parameters.map(m=>{const h=se(d.id,m);return e.jsxs("label",{className:"ec-field ec-check-row",children:[e.jsx("input",{type:"checkbox",checked:we(t,h),onChange:()=>i(h)}),e.jsx("span",{className:"ec-grow ec-truncate",children:$(m.label,a,m.key)}),e.jsx("span",{className:"ec-field-desc ec-muted",children:m.section?`${m.section} · ${m.key}`:m.key})]},h)})})]},d.id))})}function Hr({targetNames:r,paramCount:t,started:n,rows:s,expected:a,done:l}){const{t:i}=T();if(!n)return e.jsxs("div",{className:"ec-stack",children:[e.jsx("p",{children:i("copy.preview_summary",{params:t,servers:r.length})}),e.jsx("div",{className:"ec-list",children:r.map(d=>e.jsx("div",{className:"ec-server-row",children:e.jsx("span",{className:"ec-grow ec-truncate",children:d})},d))})]});const o=s.filter(d=>d.status==="success").length,u=s.length-o;return e.jsx("div",{className:"ec-stack",children:l?u>0?e.jsx(V,{variant:"warning",children:i("copy.recap_partial",{ok:o,fail:u})}):e.jsx(V,{variant:"success",children:i("copy.recap_success",{ok:o})}):e.jsxs("div",{className:"ec-row",children:[e.jsx(te,{})," ",e.jsxs("span",{children:[i("copy.in_progress")," (",s.length,"/",a,")"]})]})})}function Wr({targets:r,selected:t,onToggle:n,loading:s}){const{t:a}=T(),[l,i]=p.useState(""),o=r.filter(u=>u.name.toLowerCase().includes(l.trim().toLowerCase()));return s?e.jsxs("div",{className:"ec-row ec-muted",children:[e.jsx(te,{})," ",a("common.loading")]}):r.length===0?e.jsx("div",{className:"ec-empty",children:a("copy.no_targets")}):e.jsxs("div",{className:"ec-stack",children:[e.jsxs("div",{className:"ec-search",children:[e.jsx("span",{className:"ec-search-icon",children:e.jsx(xe,{size:14})}),e.jsx(F,{value:l,placeholder:a("copy.search_servers"),onChange:u=>i(u.target.value)})]}),e.jsx("div",{className:"ec-egg-list",children:o.map(u=>{const d=t.has(u.id);return e.jsxs("button",{type:"button",disabled:u.running,title:u.running?a("copy.running_tip"):void 0,className:S("ec-server-row",d&&"ec-server-row-on",u.running&&"ec-server-row-disabled"),onClick:()=>!u.running&&n(u.id),children:[u.egg.banner_image?e.jsx("img",{className:"ec-server-thumb",src:u.egg.banner_image,alt:""}):e.jsx("span",{className:"ec-server-thumb"}),e.jsxs("span",{className:"ec-grow",children:[e.jsx("span",{className:"ec-truncate",children:u.name}),e.jsxs("span",{className:"ec-field-desc ec-muted",children:[u.egg.name??"?"," · ",u.identifier]})]}),u.running?e.jsx(V,{variant:"warning",children:a("copy.running")}):d&&e.jsx(me,{size:16})]},u.id)})})]})}function Ur(r,t){return z.useQuery({queryKey:["ec-copy-targets",r],enabled:t,staleTime:3e4,queryFn:()=>M(`${O}/servers/${r}/copy/targets`).then(n=>n.data)})}function Br(r){return z.useMutation({mutationFn:t=>M(`${O}/servers/${r}/copy`,{method:"POST",body:JSON.stringify(t)}).then(n=>n.data)})}function Gr(r,t,n){return z.useQuery({queryKey:["ec-copy-log",r,t],enabled:t!==null,refetchInterval:s=>{var a;return(((a=s.state.data)==null?void 0:a.length)??0)>=n?!1:1500},queryFn:()=>M(`${O}/servers/${r}/copy/log?batch_id=${t??""}`).then(s=>s.data)})}function Xr(r,t){const n=[];for(const s of r)for(const a of s.files){const l=a.parameters.filter(i=>t[se(a.id,i)]??!0).map(i=>({key:i.key,section:i.section}));l.length>0&&n.push({id:a.id,params:l})}return n}function Qr({open:r,onClose:t,serverId:n,templates:s}){const{t:a}=T(),l=pe(),[i,o]=p.useState(1),[u,d]=p.useState(new Set),[m,h]=p.useState({}),[j,b]=p.useState(null),[g,x]=p.useState(0),P=Ur(n,r),L=Br(n),Y=Gr(n,j,g),Q=p.useMemo(()=>Xr(s,m),[s,m]),ae=Q.reduce((w,K)=>w+K.params.length,0),U=Y.data??[],B=j!==null&&g>0&&U.length>=g,G=(P.data??[]).filter(w=>u.has(w.id)).map(w=>w.name);p.useEffect(()=>{if(!B)return;const w=U.filter(f=>f.status==="success").length,K=U.length-w;K>0?l.warning(a("copy.recap_partial",{ok:w,fail:K})):l.success(a("copy.recap_success",{ok:w}))},[B]);const Z=()=>{o(1),d(new Set),h({}),b(null),x(0)},J=()=>{Z(),t()},q=w=>{d(K=>{const f=new Set(K);return f.has(w)?f.delete(w):f.add(w),f})},de=()=>{L.mutate({targets:[...u],files:Q,copy_boosts:!1},{onSuccess:w=>{b(w.batch_id),x(w.targets),l.show(a("copy.in_progress"))},onError:()=>l.error(a("errors.generic"))})},W=j!==null?e.jsx(R,{onClick:J,children:a("common.close")}):i===1?e.jsxs(e.Fragment,{children:[e.jsx(R,{variant:"ghost",onClick:J,children:a("common.cancel")}),e.jsx(R,{disabled:u.size===0,onClick:()=>o(2),children:a("copy.next")})]}):i===2?e.jsxs(e.Fragment,{children:[e.jsx(R,{variant:"ghost",onClick:()=>o(1),children:a("common.back")}),e.jsx(R,{disabled:ae===0,onClick:()=>o(3),children:a("copy.next")})]}):e.jsxs(e.Fragment,{children:[e.jsx(R,{variant:"ghost",onClick:()=>o(2),children:a("common.back")}),e.jsx(R,{loading:L.isPending,onClick:de,children:a("copy.confirm")})]});return e.jsx(Ke,{open:r,onClose:J,closeLabel:a("common.close"),title:a("copy.title"),size:"lg",footer:W,children:e.jsxs("div",{className:"ec-dialog-body",children:[e.jsx("div",{className:"ec-steps",children:[1,2,3].map(w=>e.jsxs("span",{className:"ec-row",children:[e.jsx("span",{className:S("ec-step-dot",i>=w&&"ec-step-dot-active"),children:w}),w<3&&e.jsx("span",{className:"ec-step-bar"})]},w))}),i===1&&e.jsx(Wr,{targets:P.data??[],selected:u,onToggle:q,loading:P.isLoading}),i===2&&e.jsx(Kr,{templates:s,selected:m,setSelected:h}),i===3&&e.jsx(Hr,{targetNames:G,paramCount:ae,started:j!==null,rows:U,expected:g,done:B})]})})}function Zr({title:r,storageKey:t,count:n,children:s}){const[a,l]=p.useState(()=>{try{return localStorage.getItem(t)!=="0"}catch{return!0}}),i=()=>{l(o=>{const u=!o;try{localStorage.setItem(t,u?"1":"0")}catch{}return u})};return e.jsxs("div",{className:S("ec-section-group",!a&&"ec-section-collapsed"),children:[e.jsxs("button",{type:"button",className:"ec-section-head",onClick:i,"aria-expanded":a,children:[e.jsx("span",{className:"ec-section-chevron",children:e.jsx(nr,{size:16})}),e.jsx("span",{children:r}),n!==void 0&&e.jsx("span",{className:"ec-section-count",children:n})]}),a&&e.jsx("div",{className:"ec-section-body",children:s})]})}function qr(r){const t=new Map;for(const n of r){const s=t.get(n.section)??[];s.push(n),t.set(n.section,s)}return[...t.entries()]}function et({file:r,controller:t,serverId:n}){const{t:s,lang:a}=T(),l=$(r.label,a,r.path),i=t.search.trim().toLowerCase(),o=m=>{var h;return i===""?!0:$(m.label,a,m.key).toLowerCase().includes(i)||m.key.toLowerCase().includes(i)||(((h=m.section)==null?void 0:h.toLowerCase().includes(i))??!1)},u=r.parameters.filter(o),d=m=>{const h=se(r.id,m);return e.jsx(Le,{param:m,value:t.getValue(h),dirty:t.isDirty(h),saved:t.isSaved(h),invalid:t.isInvalid(h),disabled:t.disabled,onChange:j=>t.onChange(h,m,j),onReset:()=>t.onReset(h,m)},h)};return e.jsxs("div",{className:"ec-stack",children:[e.jsxs("div",{className:"ec-between",children:[e.jsx("h3",{className:"ec-title",children:l}),!r.exists&&e.jsx(V,{variant:"warning",children:s("file.missing_badge")})]}),r.exists?u.length===0?e.jsx(D,{children:e.jsx(le,{children:s("section.no_results")})}):r.sectioned?qr(u).map(([m,h])=>e.jsx(Zr,{title:m??s("section.general"),storageKey:`ec:col:${n}:${r.id}:${m??""}`,count:h.length,children:h.map(d)},m??"_general")):e.jsx("div",{className:"ec-section-group",children:e.jsx("div",{className:"ec-section-body",children:u.map(d)})}):e.jsx(D,{children:e.jsx(le,{children:s("file.missing",{path:r.path})})})]})}function rt({saving:r,saved:t,onSave:n}){const{t:s}=T();return e.jsxs("div",{className:"ec-save-bar",children:[e.jsx("span",{className:"ec-save-bar-text",children:s(t?"save.saved":"save.unsaved")}),e.jsxs(R,{onClick:n,loading:r,disabled:t,children:[t?e.jsx(me,{size:15}):e.jsx(ze,{size:15}),s(t?"save.saved":"save.save")]})]})}function tt(r){return z.useQuery({queryKey:["ec-config",r],staleTime:1/0,queryFn:()=>M(`${O}/servers/${r}/config`).then(t=>t.data)})}function nt(r,t){return z.useQuery({queryKey:["ec-status",r],enabled:t,refetchInterval:n=>{var s;return((s=n.state.data)==null?void 0:s.state)==="offline"?!1:5e3},queryFn:()=>M(`${O}/servers/${r}/status`).then(n=>n.data)})}function st(r){return z.useMutation({mutationFn:t=>M(`${O}/servers/${r}/config`,{method:"PUT",body:JSON.stringify({files:t})})})}function at(r){return z.useMutation({mutationFn:t=>M(`${O}/servers/${r}/power`,{method:"POST",body:JSON.stringify({signal:t})})})}function ot({serverId:r,templates:t,disabled:n}){const{t:s,lang:a}=T(),l=pe(),i=st(r),{initial:o,index:u}=p.useMemo(()=>{const f={},y=new Map;for(const N of t)for(const A of N.files)for(const I of A.parameters){const ee=se(A.id,I);f[ee]=I.value,y.set(ee,{param:I,fileId:A.id})}return{initial:f,index:y}},[t]),[d,m]=p.useState(o),[h,j]=p.useState(o),[b,g]=p.useState({}),[x,P]=p.useState(new Set),[L,Y]=p.useState(!1),[Q,ae]=p.useState(""),[U,B]=p.useState(!1),G=p.useMemo(()=>Object.keys(d).filter(f=>d[f]!==h[f]),[d,h]),Z=G.length>0,J=Object.values(b).some(Boolean),q=p.useCallback((f,y,N)=>{Y(!1),m(I=>({...I,[f]:N}));const A=Jr(y,N);g(I=>({...I,[f]:A!==null})),A!==null&&l.warning(s("validation.invalid_value",{param:$(y.label,a,y.key),type:s(`validation.type.${A}`)}))},[l,s,a]),de=p.useCallback((f,y)=>{y.config.default!==void 0&&q(f,y,String(y.config.default))},[q]),W=p.useCallback(()=>{if(!Z||n||i.isPending)return;if(J){l.error(s("save.fix_invalid"));return}const f=new Map;for(const y of G){const N=u.get(y);if(N===void 0)continue;const A=f.get(N.fileId)??[];A.push({key:N.param.key,section:N.param.section,value:d[y]??""}),f.set(N.fileId,A)}i.mutate([...f.entries()].map(([y,N])=>({id:y,values:N})),{onSuccess:()=>{j({...d}),P(new Set(G)),Y(!0),g({}),window.setTimeout(()=>{Y(!1),P(new Set)},2e3),l.success(s("save.saved"))},onError:y=>{const N=y;if(N.status===422&&N.fields){const A={};for(const[I,ee]of Object.entries(N.fields))for(const c of Object.keys(ee))A[Yr(I,c)]=!0;g(A)}l.error(s("save.error"))}})},[Z,n,J,G,u,d,i,l,s]);p.useEffect(()=>{const f=y=>{(y.metaKey||y.ctrlKey)&&y.key.toLowerCase()==="s"&&(y.preventDefault(),W())};return window.addEventListener("keydown",f),()=>window.removeEventListener("keydown",f)},[W]);const w={getValue:f=>d[f]??"",isDirty:f=>d[f]!==h[f],isSaved:f=>x.has(f),isInvalid:f=>b[f]??!1,disabled:n,search:Q,onChange:q,onReset:de},K=t.flatMap(f=>f.files.map(y=>({key:`${f.id}:${y.id}`,file:y})));return e.jsxs("div",{className:"ec-stack",children:[e.jsxs("div",{className:"ec-between",children:[e.jsxs("div",{className:"ec-row",children:[e.jsx("span",{className:"ec-icon-box",children:e.jsx(fr,{size:18})}),e.jsxs("div",{children:[e.jsx("h2",{className:"ec-title",children:s("section.title")}),e.jsx("p",{className:"ec-subtitle",children:s("section.subtitle")})]})]}),e.jsxs(R,{variant:"secondary",onClick:()=>B(!0),children:[e.jsx(cr,{size:15})," ",s("copy.button")]})]}),e.jsxs("div",{className:"ec-search",children:[e.jsx("span",{className:"ec-search-icon",children:e.jsx(xe,{size:14})}),e.jsx(F,{value:Q,placeholder:s("section.search"),onChange:f=>ae(f.target.value)})]}),K.map(({key:f,file:y})=>e.jsx(et,{file:y,controller:w,serverId:r},f)),(Z||L)&&!n&&e.jsx(rt,{saving:i.isPending,saved:L,onSave:W}),e.jsx(Qr,{open:U,onClose:()=>B(!1),serverId:r,templates:t})]})}function ct({state:r,onStop:t,stopping:n}){const{t:s}=T();return e.jsx("div",{className:"ec-overlay",children:e.jsxs("div",{className:"ec-overlay-card",children:[e.jsx("span",{className:"ec-icon-box",children:e.jsx(Pe,{size:20})}),e.jsx("p",{className:"ec-title",children:s("overlay.running_title")}),e.jsx("p",{className:"ec-subtitle",children:s("overlay.running_desc")}),e.jsxs(R,{onClick:t,loading:n||r==="stopping",children:[e.jsx(mr,{size:15})," ",s("overlay.stop_button")]})]})})}function it({serverId:r}){var u,d;const{t}=T(),n=tt(r),s=(((u=n.data)==null?void 0:u.templates.length)??0)>0,a=nt(r,s),l=at(r);if(n.isLoading)return e.jsxs("div",{className:"ec-card ec-row ec-muted",children:[e.jsx(te,{})," ",t("common.loading")]});if(n.isError||!n.data||!s)return null;const i=((d=a.data)==null?void 0:d.state)??"offline",o=a.isSuccess&&i!=="offline";return e.jsxs("div",{className:"ec-relative",children:[e.jsx(ot,{serverId:r,templates:n.data.templates,disabled:o},r),o&&e.jsx(ct,{state:i,stopping:l.isPending,onStop:()=>l.mutate("stop")})]})}function lt({serverId:r}){return e.jsx(Oe,{children:e.jsx("div",{className:"ec-root",children:e.jsx(it,{serverId:r})})})}const dt=`
.ec-page { max-width: var(--layout-container-max, 1100px); margin: 0 auto; padding: 1.5rem; display: flex; flex-direction: column; gap: 1.25rem; }
.ec-grid { display: grid; gap: 0.85rem; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); }
.ec-list { display: flex; flex-direction: column; gap: 0.5rem; }
.ec-egg-list { display: flex; flex-direction: column; gap: 0.4rem; max-height: 18rem; overflow-y: auto; padding-right: 0.25rem; }
.ec-mono { font-family: var(--font-mono); font-size: 0.78rem; line-height: 1.5; min-height: 22rem; white-space: pre; tab-size: 2; }
.ec-field-group { display: flex; flex-direction: column; gap: 0.35rem; }
.ec-field-group > label { font-size: 0.75rem; font-weight: 600; color: var(--color-text-secondary); }
.ec-cols-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.ec-divider { height: 1px; background: var(--color-border); border: none; margin: 0.25rem 0; }
.ec-error-list { margin: 0; padding-left: 1.1rem; color: var(--color-danger); font-size: 0.78rem; display: flex; flex-direction: column; gap: 0.2rem; }
.ec-template-card { display: flex; flex-direction: column; gap: 0.6rem; }
.ec-template-card-foot { display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap; }
@media (max-width: 640px) { .ec-cols-2 { grid-template-columns: 1fr; } }
`,ut=`
.ec-root {
    font-family: var(--font-sans);
    color: var(--color-text-primary);
    font-size: 0.875rem;
    line-height: 1.5;
}
.ec-root *, .ec-root *::before, .ec-root *::after { box-sizing: border-box; }

.ec-stack { display: flex; flex-direction: column; gap: 1.25rem; }
.ec-row { display: flex; align-items: center; gap: 0.625rem; }
.ec-between { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap; }
.ec-grow { flex: 1 1 auto; min-width: 0; }
.ec-muted { color: var(--color-text-muted); }
.ec-secondary { color: var(--color-text-secondary); }
.ec-truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.ec-title { font-size: 1.125rem; font-weight: 700; margin: 0; line-height: 1.3; }
.ec-subtitle { font-size: 0.8125rem; color: var(--color-text-muted); margin: 0; }
.ec-section-label { font-size: 0.6875rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-text-muted); margin: 0; }

.ec-card {
    border-radius: var(--radius-lg);
    border: 1px solid var(--color-border);
    background: var(--color-surface);
    padding: 1rem;
    transition: border-color var(--transition-base), box-shadow var(--transition-base);
}
.ec-card-hover:hover { border-color: var(--color-border-hover); box-shadow: var(--shadow-md); }

.ec-icon-box {
    width: 40px; height: 40px; flex-shrink: 0;
    border-radius: var(--radius-lg);
    background: rgba(var(--color-primary-rgb), 0.1);
    color: var(--color-primary);
    display: flex; align-items: center; justify-content: center;
}

.ec-btn {
    appearance: none; border: none; cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;
    padding: 0.5rem 0.9rem; font-size: 0.8125rem; font-weight: 600; font-family: inherit;
    border-radius: var(--radius);
    transition: background var(--transition-fast), opacity var(--transition-fast), transform var(--transition-fast), box-shadow var(--transition-fast);
}
.ec-btn:active { transform: scale(0.97); }
.ec-btn:disabled { opacity: 0.45; cursor: not-allowed; }
.ec-btn:focus-visible { outline: none; box-shadow: 0 0 0 2px var(--color-background), 0 0 0 4px var(--color-ring); }
.ec-btn-primary { background: var(--color-primary); color: #fff; box-shadow: 0 2px 8px var(--color-primary-glow); }
.ec-btn-primary:hover:not(:disabled) { background: var(--color-primary-hover); box-shadow: 0 0 20px var(--color-primary-glow); }
.ec-btn-secondary { background: var(--color-surface); color: var(--color-text-primary); border: 1px solid var(--color-border-hover); }
.ec-btn-secondary:hover:not(:disabled) { background: var(--color-surface-hover); border-color: var(--color-text-secondary); }
.ec-btn-ghost { background: transparent; color: var(--color-text-secondary); font-weight: 500; }
.ec-btn-ghost:hover:not(:disabled) { background: var(--color-surface-hover); color: var(--color-text-primary); }
.ec-btn-danger { background: rgba(var(--color-danger-rgb), 0.12); color: var(--color-danger); border: 1px solid rgba(var(--color-danger-rgb), 0.2); }
.ec-btn-danger:hover:not(:disabled) { background: rgba(var(--color-danger-rgb), 0.2); }
.ec-btn-sm { padding: 0.35rem 0.6rem; font-size: 0.75rem; }
.ec-btn-icon { padding: 0; width: 32px; height: 32px; background: transparent; color: var(--color-text-secondary); border: 1px solid var(--color-border); }
.ec-btn-icon:hover:not(:disabled) { background: var(--color-surface-hover); color: var(--color-text-primary); }

.ec-input, .ec-select, .ec-textarea {
    width: 100%; font-family: inherit; font-size: 0.8125rem; color: var(--color-text-primary);
    background: var(--color-background); border: 1px solid var(--color-border);
    border-radius: var(--radius); padding: 0.5rem 0.7rem; outline: none;
    transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
}
.ec-textarea { resize: vertical; min-height: 4.5rem; line-height: 1.45; }
.ec-input:focus, .ec-select:focus, .ec-textarea:focus { border-color: var(--color-primary); box-shadow: 0 0 0 3px var(--color-primary-glow); }
.ec-input-invalid { border-color: var(--color-danger); }
.ec-select { cursor: pointer; appearance: none; padding-right: 1.9rem;
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%238b849e' stroke-width='2'><polyline points='6 9 12 15 18 9'/></svg>");
    background-repeat: no-repeat; background-position: right 0.6rem center; }

.ec-badge { display: inline-flex; align-items: center; gap: 0.25rem; border-radius: var(--radius-full);
    padding: 0.1rem 0.55rem; font-size: 0.6875rem; font-weight: 600; line-height: 1.4; white-space: nowrap; }
.ec-badge-accent { background: rgba(var(--color-accent-rgb), 0.15); color: var(--color-accent); }
.ec-badge-info { background: rgba(var(--color-info-rgb), 0.15); color: var(--color-info); }
.ec-badge-warning { background: rgba(var(--color-warning-rgb), 0.15); color: var(--color-warning); }
.ec-badge-success { background: rgba(var(--color-success-rgb), 0.15); color: var(--color-success); }
.ec-badge-muted { background: var(--surface-overlay-soft); color: var(--color-text-secondary); }

.ec-spinner { width: 1rem; height: 1rem; border-radius: var(--radius-full);
    border: 2px solid var(--surface-overlay-strong); border-top-color: var(--color-primary);
    animation: ec-spin 0.7s linear infinite; display: inline-block; }
.ec-spinner-lg { width: 1.75rem; height: 1.75rem; border-width: 3px; }

.ec-tabs { display: flex; gap: 0.25rem; border-bottom: 1px solid var(--color-border); }
.ec-tab { appearance: none; border: none; background: transparent; cursor: pointer; font-family: inherit;
    padding: 0.5rem 0.85rem; font-size: 0.8125rem; font-weight: 500; color: var(--color-text-secondary);
    border-bottom: 2px solid transparent; margin-bottom: -1px; transition: color var(--transition-fast), border-color var(--transition-fast); }
.ec-tab:hover { color: var(--color-text-primary); }
.ec-tab-active { color: var(--color-primary); border-bottom-color: var(--color-primary); }

.ec-empty { padding: 2.5rem 1rem; text-align: center; color: var(--color-text-muted); font-size: 0.8125rem; }

.ec-root input[type="checkbox"] { accent-color: var(--color-primary); cursor: pointer; width: 1rem; height: 1rem; flex-shrink: 0; }
.ec-check-row { cursor: pointer; }
.ec-check-row:hover { background: var(--color-surface-hover); }

@keyframes ec-spin { to { transform: rotate(360deg); } }
@keyframes ec-fade-in { from { opacity: 0; } to { opacity: 1; } }
@keyframes ec-pop-in { from { opacity: 0; transform: translateY(6px) scale(0.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
@keyframes ec-slide-up { from { opacity: 0; transform: translate(-50%, 1rem); } to { opacity: 1; transform: translate(-50%, 0); } }
`,mt=`
.ec-section-group { border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden; background: var(--color-surface); }
.ec-section-head { display: flex; align-items: center; gap: 0.5rem; width: 100%; padding: 0.75rem 1rem;
    background: transparent; border: none; cursor: pointer; font-family: inherit; color: var(--color-text-primary);
    font-size: 0.8125rem; font-weight: 600; text-align: left; }
.ec-section-head:hover { background: var(--color-surface-hover); }
.ec-section-chevron { transition: transform var(--transition-base); color: var(--color-text-muted); display: inline-flex; }
.ec-section-collapsed .ec-section-chevron { transform: rotate(-90deg); }
.ec-section-body { display: flex; flex-direction: column; }
.ec-section-count { margin-left: auto; font-size: 0.6875rem; color: var(--color-text-muted); font-weight: 500; }

.ec-field { display: flex; align-items: center; gap: 1rem; padding: 0.85rem 1rem; border-top: 1px solid var(--color-border); position: relative; }
.ec-field:first-child { border-top: none; }
.ec-field-dirty { box-shadow: inset 3px 0 0 var(--color-accent); }
.ec-field-label-col { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; flex: 1 1 50%; }
.ec-field-label { font-weight: 500; display: inline-flex; align-items: center; gap: 0.4rem; }
.ec-field-desc { font-size: 0.75rem; color: var(--color-text-muted); }
.ec-field-control { flex: 1 1 45%; display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem; min-width: 0; }
.ec-field-value { color: var(--color-accent); font-weight: 600; font-variant-numeric: tabular-nums; }
.ec-field-inferred { font-style: italic; }
.ec-field-saved { color: var(--color-success); display: inline-flex; animation: ec-fade-in var(--transition-base); }

.ec-help { color: var(--color-text-muted); display: inline-flex; cursor: help; }

.ec-reset { opacity: 0; transition: opacity var(--transition-fast); }
.ec-field:hover .ec-reset { opacity: 1; }

/* Toggle */
.ec-toggle { position: relative; width: 2.5rem; height: 1.4rem; border-radius: var(--radius-full); border: none; cursor: pointer;
    background: var(--color-border-hover); transition: background var(--transition-base); flex-shrink: 0; }
.ec-toggle-on { background: var(--color-primary); }
.ec-toggle-knob { position: absolute; top: 2px; left: 2px; width: calc(1.4rem - 4px); height: calc(1.4rem - 4px);
    border-radius: var(--radius-full); background: #fff; transition: transform var(--transition-base); }
.ec-toggle-on .ec-toggle-knob { transform: translateX(1.1rem); }
.ec-toggle:disabled { opacity: 0.5; cursor: not-allowed; }

/* Slider */
.ec-slider-wrap { display: flex; align-items: center; gap: 0.75rem; width: 100%; }
.ec-slider { -webkit-appearance: none; appearance: none; flex: 1 1 auto; height: 0.35rem; border-radius: var(--radius-full);
    background: var(--color-border-hover); outline: none; cursor: pointer; }
.ec-slider::-webkit-slider-thumb { -webkit-appearance: none; appearance: none; width: 1rem; height: 1rem; border-radius: var(--radius-full);
    background: var(--color-primary); border: 2px solid var(--color-surface); box-shadow: var(--shadow-sm); cursor: pointer; }
.ec-slider::-moz-range-thumb { width: 1rem; height: 1rem; border-radius: var(--radius-full); background: var(--color-primary);
    border: 2px solid var(--color-surface); cursor: pointer; }
.ec-slider-number { width: 5rem; flex-shrink: 0; text-align: right; }
.ec-input-narrow { max-width: 8rem; }

/* Color */
.ec-color { display: flex; align-items: center; gap: 0.5rem; }
.ec-color-swatch { width: 1.6rem; height: 1.6rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border); padding: 0; cursor: pointer; background: none; }

/* Search */
.ec-search { position: relative; }
.ec-search .ec-input { padding-left: 2rem; }
.ec-search-icon { position: absolute; left: 0.6rem; top: 50%; transform: translateY(-50%); color: var(--color-text-muted); pointer-events: none; }

/* Multiselect chips */
.ec-chips { display: flex; flex-wrap: wrap; gap: 0.35rem; justify-content: flex-end; }
.ec-chip { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.15rem 0.5rem; border-radius: var(--radius-full);
    font-size: 0.7rem; cursor: pointer; border: 1px solid var(--color-border); background: var(--color-background); color: var(--color-text-secondary); transition: all var(--transition-fast); }
.ec-chip-on { background: rgba(var(--color-primary-rgb), 0.15); border-color: var(--color-primary); color: var(--color-primary); }
`,pt=`
.ec-scrim { position: fixed; inset: 0; z-index: 60; background: var(--modal-scrim);
    backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; padding: 1rem;
    animation: ec-fade-in var(--transition-fast); }
.ec-dialog { width: 100%; max-width: 560px; max-height: calc(100vh - 2rem); overflow-y: auto;
    border-radius: var(--radius-lg); border: 1px solid var(--color-border); background: var(--color-surface);
    box-shadow: var(--shadow-lg); display: flex; flex-direction: column; animation: ec-pop-in var(--transition-base); }
.ec-dialog-lg { max-width: 760px; }
.ec-dialog-head { display: flex; align-items: center; gap: 0.6rem; padding: 1rem 1.25rem; border-bottom: 1px solid var(--color-border); }
.ec-dialog-title { font-size: 0.95rem; font-weight: 700; margin: 0; }
.ec-dialog-body { padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem; }
.ec-dialog-foot { display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem;
    padding: 1rem 1.25rem; border-top: 1px solid var(--color-border); }

.ec-steps { display: flex; align-items: center; gap: 0.4rem; }
.ec-step-dot { width: 1.5rem; height: 1.5rem; border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center;
    font-size: 0.7rem; font-weight: 600; background: var(--surface-overlay-soft); color: var(--color-text-muted); }
.ec-step-dot-active { background: var(--color-primary); color: #fff; }
.ec-step-bar { flex: 1; height: 2px; background: var(--color-border); }

.ec-save-bar { position: fixed; bottom: 1.5rem; left: 50%; transform: translateX(-50%); z-index: 50;
    display: flex; align-items: center; gap: 0.85rem; padding: 0.6rem 0.7rem 0.6rem 1.1rem;
    border-radius: var(--radius-full); border: 1px solid var(--color-glass-border);
    background: var(--color-glass); backdrop-filter: var(--glass-blur); box-shadow: var(--shadow-lg);
    animation: ec-slide-up var(--transition-smooth); }
.ec-save-bar-text { font-size: 0.8125rem; font-weight: 500; }

.ec-overlay { position: absolute; inset: 0; z-index: 20; border-radius: var(--radius-lg);
    background: var(--ambient-overlay); backdrop-filter: blur(2px);
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.85rem; text-align: center; padding: 1.5rem; }
.ec-overlay-card { display: flex; flex-direction: column; align-items: center; gap: 0.85rem; max-width: 22rem; }
.ec-relative { position: relative; }

.ec-toast-host { position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 80; display: flex; flex-direction: column; gap: 0.5rem; max-width: 22rem; }
.ec-toast { display: flex; align-items: flex-start; gap: 0.5rem; padding: 0.7rem 0.85rem; border-radius: var(--radius);
    border: 1px solid var(--color-border); background: var(--color-glass); backdrop-filter: var(--glass-blur);
    box-shadow: var(--shadow-md); font-size: 0.8125rem; animation: ec-pop-in var(--transition-base); }
.ec-toast-error { border-color: rgba(var(--color-danger-rgb), 0.4); }
.ec-toast-success { border-color: rgba(var(--color-success-rgb), 0.4); }
.ec-toast-icon { flex-shrink: 0; margin-top: 0.05rem; }
.ec-toast-error .ec-toast-icon { color: var(--color-danger); }
.ec-toast-success .ec-toast-icon { color: var(--color-success); }
.ec-toast-warning .ec-toast-icon { color: var(--color-warning); }

.ec-tooltip { position: relative; display: inline-flex; }
.ec-tooltip-pop { position: absolute; bottom: calc(100% + 0.4rem); left: 50%; transform: translateX(-50%);
    background: var(--color-surface-elevated); color: var(--color-text-primary); border: 1px solid var(--color-border);
    border-radius: var(--radius-sm); padding: 0.4rem 0.6rem; font-size: 0.7rem; font-weight: 400; white-space: normal; width: max-content; max-width: 16rem;
    box-shadow: var(--shadow-md); z-index: 70; opacity: 0; pointer-events: none; transition: opacity var(--transition-fast); }
.ec-tooltip:hover .ec-tooltip-pop, .ec-tooltip:focus-within .ec-tooltip-pop { opacity: 1; }

.ec-server-row { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 0.75rem; border-radius: var(--radius);
    border: 1px solid var(--color-border); cursor: pointer; transition: all var(--transition-fast); background: var(--color-background); }
.ec-server-row:hover:not(.ec-server-row-disabled) { border-color: var(--color-border-hover); }
.ec-server-row-on { border-color: var(--color-primary); background: rgba(var(--color-primary-rgb), 0.08); }
.ec-server-row-disabled { opacity: 0.5; cursor: not-allowed; }
.ec-server-thumb { width: 2.5rem; height: 2.5rem; border-radius: var(--radius); object-fit: cover; flex-shrink: 0; background: var(--color-surface-elevated); }
`,He="easy-config-styles";function ft(){if(typeof document>"u"||document.getElementById(He))return;const r=document.createElement("style");r.id=He,r.textContent=[ut,mt,pt,dt].join(`
`),document.head.appendChild(r)}ft(),Me.register("easy-configuration",Dr),Me.registerServerHomeSection("easy-config",lt)})(window.__PEREGRINE_SHARED__.React,window.__PEREGRINE_SHARED__.ReactRouterDom,window.__PEREGRINE_SHARED__,window.__PEREGRINE_SHARED__.ReactQuery);
