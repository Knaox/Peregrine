(function(f,V,Ke,T){"use strict";var ae={exports:{}},G={};/**
 * @license React
 * react-jsx-runtime.production.js
 *
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */var je;function He(){if(je)return G;je=1;var r=Symbol.for("react.transitional.element"),t=Symbol.for("react.fragment");function a(n,o,c){var l=null;if(c!==void 0&&(l=""+c),o.key!==void 0&&(l=""+o.key),"key"in o){c={};for(var i in o)i!=="key"&&(c[i]=o[i])}else c=o;return o=c.ref,{$$typeof:r,type:n,key:l,ref:o!==void 0?o:null,props:c}}return G.Fragment=t,G.jsx=a,G.jsxs=a,G}var X={};/**
 * @license React
 * react-jsx-runtime.development.js
 *
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */var we;function We(){return we||(we=1,process.env.NODE_ENV!=="production"&&(function(){function r(s){if(s==null)return null;if(typeof s=="function")return s.$$typeof===xe?null:s.displayName||s.name||null;if(typeof s=="string")return s;switch(s){case P:return"Fragment";case Y:return"Profiler";case I:return"StrictMode";case Q:return"Suspense";case ie:return"SuspenseList";case re:return"Activity"}if(typeof s=="object")switch(typeof s.tag=="number"&&console.error("Received an unexpected object in getComponentNameFromType(). This is likely a bug in React. Please file an issue."),s.$$typeof){case v:return"Portal";case ve:return s.displayName||"Context";case se:return(s._context.displayName||"Context")+".Consumer";case U:var p=s.render;return s=s.displayName,s||(s=p.displayName||p.name||"",s=s!==""?"ForwardRef("+s+")":"ForwardRef"),s;case q:return p=s.displayName||null,p!==null?p:r(s.type)||"Memo";case ee:p=s._payload,s=s._init;try{return r(s(p))}catch{}}return null}function t(s){return""+s}function a(s){try{t(s);var p=!1}catch{p=!0}if(p){p=console;var j=p.error,N=typeof Symbol=="function"&&Symbol.toStringTag&&s[Symbol.toStringTag]||s.constructor.name||"Object";return j.call(p,"The provided key is an unsupported type %s. This value must be coerced to a string before using it here.",N),t(s)}}function n(s){if(s===P)return"<>";if(typeof s=="object"&&s!==null&&s.$$typeof===ee)return"<...>";try{var p=r(s);return p?"<"+p+">":"<...>"}catch{return"<...>"}}function o(){var s=te.A;return s===null?null:s.getOwner()}function c(){return Error("react-stack-top-frame")}function l(s){if(g.call(s,"key")){var p=Object.getOwnPropertyDescriptor(s,"key").get;if(p&&p.isReactWarning)return!1}return s.key!==void 0}function i(s,p){function j(){C||(C=!0,console.error("%s: `key` is not a prop. Trying to access it will result in `undefined` being returned. If you need to access the same value within the child component, you should pass it as a different prop. (https://react.dev/link/special-props)",p))}j.isReactWarning=!0,Object.defineProperty(s,"key",{get:j,configurable:!0})}function u(){var s=r(this.type);return O[s]||(O[s]=!0,console.error("Accessing element.ref was removed in React 19. ref is now a regular prop. It will be removed from the JSX Element type in a future release.")),s=this.props.ref,s!==void 0?s:null}function d(s,p,j,N,le,be){var E=j.ref;return s={$$typeof:h,type:s,key:p,props:j,_owner:N},(E!==void 0?E:null)!==null?Object.defineProperty(s,"ref",{enumerable:!1,get:u}):Object.defineProperty(s,"ref",{enumerable:!1,value:null}),s._store={},Object.defineProperty(s._store,"validated",{configurable:!1,enumerable:!1,writable:!0,value:0}),Object.defineProperty(s,"_debugInfo",{configurable:!1,enumerable:!1,writable:!0,value:null}),Object.defineProperty(s,"_debugStack",{configurable:!1,enumerable:!1,writable:!0,value:le}),Object.defineProperty(s,"_debugTask",{configurable:!1,enumerable:!1,writable:!0,value:be}),Object.freeze&&(Object.freeze(s.props),Object.freeze(s)),s}function m(s,p,j,N,le,be){var E=p.children;if(E!==void 0)if(N)if(b(E)){for(N=0;N<E.length;N++)x(E[N]);Object.freeze&&Object.freeze(E)}else console.error("React.jsx: Static children should always be an array. You are likely explicitly calling React.jsxs or React.jsxDEV. Use the Babel transform instead.");else x(E);if(g.call(p,"key")){E=r(s);var B=Object.keys(p).filter(function(ot){return ot!=="key"});N=0<B.length?"{key: someKey, "+B.join(": ..., ")+": ...}":"{key: someKey}",Je[E+N]||(B=0<B.length?"{"+B.join(": ..., ")+": ...}":"{}",console.error(`A props object containing a "key" prop is being spread into JSX:
  let props = %s;
  <%s {...props} />
React keys must be passed directly to JSX without using spread:
  let props = %s;
  <%s key={someKey} {...props} />`,N,E,B,E),Je[E+N]=!0)}if(E=null,j!==void 0&&(a(j),E=""+j),l(p)&&(a(p.key),E=""+p.key),"key"in p){j={};for(var ye in p)ye!=="key"&&(j[ye]=p[ye])}else j=p;return E&&i(j,typeof s=="function"?s.displayName||s.name||"Unknown":s),d(s,E,j,o(),le,be)}function x(s){w(s)?s._store&&(s._store.validated=1):typeof s=="object"&&s!==null&&s.$$typeof===ee&&(s._payload.status==="fulfilled"?w(s._payload.value)&&s._payload.value._store&&(s._payload.value._store.validated=1):s._store&&(s._store.validated=1))}function w(s){return typeof s=="object"&&s!==null&&s.$$typeof===h}var y=f,h=Symbol.for("react.transitional.element"),v=Symbol.for("react.portal"),P=Symbol.for("react.fragment"),I=Symbol.for("react.strict_mode"),Y=Symbol.for("react.profiler"),se=Symbol.for("react.consumer"),ve=Symbol.for("react.context"),U=Symbol.for("react.forward_ref"),Q=Symbol.for("react.suspense"),ie=Symbol.for("react.suspense_list"),q=Symbol.for("react.memo"),ee=Symbol.for("react.lazy"),re=Symbol.for("react.activity"),xe=Symbol.for("react.client.reference"),te=y.__CLIENT_INTERNALS_DO_NOT_USE_OR_WARN_USERS_THEY_CANNOT_UPGRADE,g=Object.prototype.hasOwnProperty,b=Array.isArray,k=console.createTask?console.createTask:function(){return null};y={react_stack_bottom_frame:function(s){return s()}};var C,O={},K=y.react_stack_bottom_frame.bind(y,c)(),ce=k(n(c)),Je={};X.Fragment=P,X.jsx=function(s,p,j){var N=1e4>te.recentlyCreatedOwnerStacks++;return m(s,p,j,!1,N?Error("react-stack-top-frame"):K,N?k(n(s)):ce)},X.jsxs=function(s,p,j){var N=1e4>te.recentlyCreatedOwnerStacks++;return m(s,p,j,!0,N?Error("react-stack-top-frame"):K,N?k(n(s)):ce)}})()),X}var ke;function Ue(){return ke||(ke=1,process.env.NODE_ENV==="production"?ae.exports=He():ae.exports=We()),ae.exports}var e=Ue();function _e(r){var t,a,n="";if(typeof r=="string"||typeof r=="number")n+=r;else if(typeof r=="object")if(Array.isArray(r)){var o=r.length;for(t=0;t<o;t++)r[t]&&(a=_e(r[t]))&&(n&&(n+=" "),n+=a)}else for(a in r)r[a]&&(n&&(n+=" "),n+=a);return n}function S(){for(var r,t,a=0,n="",o=arguments.length;a<o;a++)(r=arguments[a])&&(t=_e(r))&&(n&&(n+=" "),n+=t);return n}/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Ne=(...r)=>r.filter((t,a,n)=>!!t&&t.trim()!==""&&n.indexOf(t)===a).join(" ").trim();/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Be=r=>r.replace(/([a-z0-9])([A-Z])/g,"$1-$2").toLowerCase();/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Ge=r=>r.replace(/^([A-Z])|[\s-_]+(\w)/g,(t,a,n)=>n?n.toUpperCase():a.toLowerCase());/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Ee=r=>{const t=Ge(r);return t.charAt(0).toUpperCase()+t.slice(1)};/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */var de={xmlns:"http://www.w3.org/2000/svg",width:24,height:24,viewBox:"0 0 24 24",fill:"none",stroke:"currentColor",strokeWidth:2,strokeLinecap:"round",strokeLinejoin:"round"};/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Xe=r=>{for(const t in r)if(t.startsWith("aria-")||t==="role"||t==="title")return!0;return!1},Ze=f.createContext({}),Qe=()=>f.useContext(Ze),qe=f.forwardRef(({color:r,size:t,strokeWidth:a,absoluteStrokeWidth:n,className:o="",children:c,iconNode:l,...i},u)=>{const{size:d=24,strokeWidth:m=2,absoluteStrokeWidth:x=!1,color:w="currentColor",className:y=""}=Qe()??{},h=n??x?Number(a??m)*24/Number(t??d):a??m;return f.createElement("svg",{ref:u,...de,width:t??d??de.width,height:t??d??de.height,stroke:r??w,strokeWidth:h,className:Ne("lucide",y,o),...!c&&!Xe(i)&&{"aria-hidden":"true"},...i},[...l.map(([v,P])=>f.createElement(v,P)),...Array.isArray(c)?c:[c]])});/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const _=(r,t)=>{const a=f.forwardRef(({className:n,...o},c)=>f.createElement(qe,{ref:c,iconNode:t,className:Ne(`lucide-${Be(Ee(r))}`,`lucide-${r}`,n),...o}));return a.displayName=Ee(r),a};/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const er=_("arrow-left",[["path",{d:"m12 19-7-7 7-7",key:"1l729n"}],["path",{d:"M19 12H5",key:"x3x0zl"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ue=_("check",[["path",{d:"M20 6 9 17l-5-5",key:"1gmf2c"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const rr=_("chevron-down",[["path",{d:"m6 9 6 6 6-6",key:"qrunsl"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const tr=_("circle-check",[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}],["path",{d:"m9 12 2 2 4-4",key:"dzmm74"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ar=_("circle-question-mark",[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}],["path",{d:"M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3",key:"1u773s"}],["path",{d:"M12 17h.01",key:"p32p05"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const nr=_("circle-x",[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}],["path",{d:"m15 9-6 6",key:"1uzhvr"}],["path",{d:"m9 9 6 6",key:"z0biqf"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const or=_("download",[["path",{d:"M12 15V3",key:"m9g1x1"}],["path",{d:"M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4",key:"ih7n3h"}],["path",{d:"m7 10 5 5 5-5",key:"brsn70"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const sr=_("file-plus-corner",[["path",{d:"M11.35 22H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.706.706l3.588 3.588A2.4 2.4 0 0 1 20 8v5.35",key:"17jvcc"}],["path",{d:"M14 2v5a1 1 0 0 0 1 1h5",key:"wfsgrz"}],["path",{d:"M14 19h6",key:"bvotb8"}],["path",{d:"M17 16v6",key:"18yu1i"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ir=_("info",[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}],["path",{d:"M12 16v-4",key:"1dtifu"}],["path",{d:"M12 8h.01",key:"e9boi3"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const cr=_("pencil",[["path",{d:"M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z",key:"1a8usu"}],["path",{d:"m15 5 4 4",key:"1mk7zo"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const lr=_("power",[["path",{d:"M12 2v10",key:"mnfbl"}],["path",{d:"M18.4 6.6a9 9 0 1 1-12.77.04",key:"obofu9"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const dr=_("rotate-ccw",[["path",{d:"M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8",key:"1357e3"}],["path",{d:"M3 3v5h5",key:"1xhq8a"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Se=_("save",[["path",{d:"M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z",key:"1c8476"}],["path",{d:"M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7",key:"1ydtos"}],["path",{d:"M7 3v4a1 1 0 0 0 1 1h7",key:"t51u73"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Ce=_("search",[["path",{d:"m21 21-4.34-4.34",key:"14j7rj"}],["circle",{cx:"11",cy:"11",r:"8",key:"4ej97u"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ur=_("sliders-horizontal",[["path",{d:"M10 5H3",key:"1qgfaw"}],["path",{d:"M12 19H3",key:"yhmn1j"}],["path",{d:"M14 3v4",key:"1sua03"}],["path",{d:"M16 17v4",key:"1q0r14"}],["path",{d:"M21 12h-9",key:"1o4lsq"}],["path",{d:"M21 19h-5",key:"1rlt1p"}],["path",{d:"M21 5h-7",key:"1oszz2"}],["path",{d:"M8 10v4",key:"tgpxqk"}],["path",{d:"M8 12H3",key:"a7s4jb"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const mr=_("trash-2",[["path",{d:"M10 11v6",key:"nco0om"}],["path",{d:"M14 11v6",key:"outv1u"}],["path",{d:"M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6",key:"miytrc"}],["path",{d:"M3 6h18",key:"d0wm0j"}],["path",{d:"M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2",key:"e791ji"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Te=_("triangle-alert",[["path",{d:"m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3",key:"wmoenq"}],["path",{d:"M12 9v4",key:"juzpu7"}],["path",{d:"M12 17h.01",key:"p32p05"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const pr=_("upload",[["path",{d:"M12 3v12",key:"1x0j5s"}],["path",{d:"m17 8-5-5-5 5",key:"7q97r8"}],["path",{d:"M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4",key:"ih7n3h"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const fr=_("x",[["path",{d:"M18 6 6 18",key:"1bl5f8"}],["path",{d:"m6 6 12 12",key:"d8bk6v"}]]),Re=f.createContext(null);function me(){const r=f.useContext(Re);if(r===null)throw new Error("useToast must be used within a ToastProvider");return r}function ze({children:r}){const[t,a]=f.useState([]),n=f.useRef(0),o=f.useCallback(i=>{a(u=>u.filter(d=>d.id!==i))},[]),c=f.useCallback((i,u="info")=>{n.current+=1;const d=n.current;a(m=>[...m,{id:d,variant:u,message:i}]),window.setTimeout(()=>o(d),4500)},[o]),l=f.useMemo(()=>({show:c,success:i=>c(i,"success"),error:i=>c(i,"error"),warning:i=>c(i,"warning")}),[c]);return e.jsxs(Re.Provider,{value:l,children:[r,t.length>0&&e.jsx("div",{className:"ec-toast-host",children:t.map(i=>e.jsxs("div",{className:S("ec-toast",`ec-toast-${i.variant}`),role:"status",onClick:()=>o(i.id),children:[e.jsx("span",{className:"ec-toast-icon",children:e.jsx(gr,{variant:i.variant})}),e.jsx("span",{children:i.message})]},i.id))})]})}function gr({variant:r}){return r==="success"?e.jsx(tr,{size:16}):r==="error"?e.jsx(nr,{size:16}):r==="warning"?e.jsx(Te,{size:16}):e.jsx(ir,{size:16})}const Ae=window.__PEREGRINE_PLUGINS__,H="easy-configuration",A=`/api/plugins/${H}`;function hr(){var r;return((r=document.querySelector('meta[name="csrf-token"]'))==null?void 0:r.getAttribute("content"))??""}async function M(r,t={}){const a=await fetch(r,{...t,credentials:"same-origin",headers:{"Content-Type":"application/json",Accept:"application/json","X-CSRF-TOKEN":hr(),...t.headers}});if(!a.ok){const o=(await a.json().catch(()=>({}))).error;throw{status:a.status,code:o==null?void 0:o.code,message:o==null?void 0:o.message,messages:o==null?void 0:o.messages,fields:o==null?void 0:o.fields}}if(a.status!==204)return await a.json()}function R(){const{t:r,i18n:t}=Ke.useTranslation(H);return{t:r,lang:(t.language??"en").slice(0,2)}}function L(r,t,a=""){return r?r[t]??r.en??r.fr??Object.values(r)[0]??a:a}function ne({size:r}){return e.jsx("span",{className:S("ec-spinner",r==="lg"&&"ec-spinner-lg"),"aria-hidden":!0})}function F({children:r,className:t,hover:a}){return e.jsx("div",{className:S("ec-card",a&&"ec-card-hover",t),children:r})}function J({variant:r="muted",children:t}){return e.jsx("span",{className:S("ec-badge",`ec-badge-${r}`),children:t})}function Z({children:r}){return e.jsx("div",{className:"ec-empty",children:r})}function vr({tabs:r,active:t,onChange:a}){return e.jsx("div",{className:"ec-tabs",role:"tablist",children:r.map(n=>e.jsx("button",{type:"button",role:"tab","aria-selected":n.id===t,className:S("ec-tab",n.id===t&&"ec-tab-active"),onClick:()=>a(n.id),children:n.label},n.id))})}function xr({content:r,children:t}){return e.jsxs("span",{className:"ec-tooltip",tabIndex:0,children:[t,e.jsx("span",{role:"tooltip",className:"ec-tooltip-pop",children:r})]})}function $({variant:r="primary",size:t="md",loading:a=!1,disabled:n,type:o="button",className:c,children:l,...i}){return e.jsxs("button",{...i,type:o,disabled:n||a,className:S("ec-btn",`ec-btn-${r}`,t==="sm"&&"ec-btn-sm",c),children:[a&&e.jsx("span",{className:"ec-spinner","aria-hidden":!0}),l]})}function pe({label:r,type:t="button",className:a,children:n,...o}){return e.jsx("button",{...o,type:t,"aria-label":r,title:r,className:S("ec-btn","ec-btn-icon",a),children:n})}function z({invalid:r,className:t,...a}){return e.jsx("input",{...a,className:S("ec-input",r&&"ec-input-invalid",t)})}function fe({invalid:r,className:t,...a}){return e.jsx("textarea",{...a,className:S("ec-textarea",r&&"ec-input-invalid",t)})}function br({value:r,onChange:t,children:a,className:n,disabled:o,invalid:c}){return e.jsx("select",{className:S("ec-select",c&&"ec-input-invalid",n),value:r,disabled:o,onChange:l=>t(l.target.value),children:a})}function Pe({checked:r,onChange:t,disabled:a,label:n}){return e.jsx("button",{type:"button",role:"switch","aria-checked":r,"aria-label":n,disabled:a,className:S("ec-toggle",r&&"ec-toggle-on"),onClick:()=>t(!r),children:e.jsx("span",{className:"ec-toggle-knob"})})}const oe=["ec-admin-templates"];function yr(){return T.useQuery({queryKey:oe,queryFn:()=>M(`${A}/admin/templates`).then(r=>r.data)})}function jr(r){return T.useQuery({queryKey:["ec-admin-template",r],enabled:r!==null,queryFn:()=>M(`${A}/admin/templates/${r??""}`).then(t=>t.data)})}function wr(){return T.useQuery({queryKey:["ec-admin-eggs"],staleTime:5*6e4,queryFn:()=>M(`${A}/admin/eggs`).then(r=>r.data)})}function kr(){const r=T.useQueryClient();return T.useMutation({mutationFn:({id:t,template:a})=>t!==null?M(`${A}/admin/templates/${t}`,{method:"PUT",body:JSON.stringify({template:a})}):M(`${A}/admin/templates`,{method:"POST",body:JSON.stringify({template:a})}),onSuccess:()=>r.invalidateQueries({queryKey:oe})})}function _r(){const r=T.useQueryClient();return T.useMutation({mutationFn:t=>M(`${A}/admin/templates/import`,{method:"POST",body:JSON.stringify({content:t})}),onSuccess:()=>r.invalidateQueries({queryKey:oe})})}function Nr(){const r=T.useQueryClient();return T.useMutation({mutationFn:t=>M(`${A}/admin/templates/${t}`,{method:"DELETE"}),onSuccess:()=>r.invalidateQueries({queryKey:oe})})}function Er({value:r,onChange:t,eggs:a,loading:n}){const{t:o}=R(),[c,l]=f.useState(""),i=a.filter(d=>d.name.toLowerCase().includes(c.trim().toLowerCase())),u=d=>{t(r.includes(d)?r.filter(m=>m!==d):[...r,d])};return e.jsxs("div",{className:"ec-field-group",children:[e.jsx("label",{children:o("admin.editor.target_eggs")}),e.jsxs("div",{className:"ec-search",children:[e.jsx("span",{className:"ec-search-icon",children:e.jsx(Ce,{size:14})}),e.jsx(z,{value:c,placeholder:o("admin.editor.search_eggs"),onChange:d=>l(d.target.value)})]}),n?e.jsxs("div",{className:"ec-row ec-muted",children:[e.jsx(ne,{})," ",o("common.loading")]}):e.jsxs("div",{className:"ec-egg-list",children:[i.map(d=>{const m=r.includes(d.id);return e.jsxs("button",{type:"button",className:S("ec-server-row",m&&"ec-server-row-on"),onClick:()=>u(d.id),children:[d.banner_image?e.jsx("img",{className:"ec-server-thumb",src:d.banner_image,alt:""}):e.jsx("span",{className:"ec-server-thumb"}),e.jsx("span",{className:"ec-grow ec-truncate",children:d.name}),e.jsxs("span",{className:"ec-muted",children:["#",d.id]}),m&&e.jsx(ue,{size:16})]},d.id)}),i.length===0&&e.jsx("div",{className:"ec-empty",children:o("admin.editor.no_eggs")})]})]})}function Sr({param:r,value:t,onChange:a,disabled:n,invalid:o}){const{lang:c}=R(),l=r.config;switch(r.display_type){case"boolean":return e.jsx(Cr,{config:l,value:t,onChange:a,disabled:n});case"slider":return e.jsx(Tr,{config:l,value:t,onChange:a,disabled:n,invalid:o});case"number":return e.jsx(z,{className:"ec-input-narrow",type:"number",min:l.min,max:l.max,step:l.step??(l.float?"any":1),value:t,disabled:n,invalid:o,onChange:i=>a(i.target.value)});case"select":return e.jsx(br,{value:t,disabled:n,invalid:o,onChange:a,children:(l.options??[]).map(i=>e.jsx("option",{value:i.value,children:L(i.label,c,i.value)},i.value))});case"multiselect":return e.jsx(Rr,{config:l,value:t,onChange:a,disabled:n});case"textarea":return e.jsx(fe,{value:t,disabled:n,invalid:o,maxLength:l.max_length,onChange:i=>a(i.target.value)});case"color":return e.jsx(zr,{value:t,onChange:a,disabled:n,invalid:o});default:return e.jsx(z,{value:t,disabled:n,invalid:o,maxLength:l.max_length,onChange:i=>a(i.target.value)})}}function Cr({config:r,value:t,onChange:a,disabled:n}){const o=r.true_value??"true",c=r.false_value??"false";return e.jsx(Pe,{checked:t===o,disabled:n,onChange:l=>a(l?o:c)})}function Tr({config:r,value:t,onChange:a,disabled:n,invalid:o}){const c=r.min??0,l=r.max??100,i=r.step??1;return e.jsxs("div",{className:"ec-slider-wrap",children:[e.jsx("input",{type:"range",className:"ec-slider",min:c,max:l,step:i,value:t,disabled:n,onChange:u=>a(u.target.value)}),e.jsx(z,{className:"ec-slider-number",type:"number",min:c,max:l,step:i,value:t,disabled:n,invalid:o,onChange:u=>a(u.target.value)})]})}function Rr({config:r,value:t,onChange:a,disabled:n}){const{lang:o}=R(),c=r.separator&&r.separator!==""?r.separator:",",l=t.split(c).map(u=>u.trim()).filter(u=>u!==""),i=u=>{const d=l.includes(u)?l.filter(m=>m!==u):[...l,u];a(d.join(c))};return e.jsx("div",{className:"ec-chips",children:(r.options??[]).map(u=>e.jsx("button",{type:"button",disabled:n,className:S("ec-chip",l.includes(u.value)&&"ec-chip-on"),onClick:()=>i(u.value),children:L(u.label,o,u.value)},u.value))})}function zr({value:r,onChange:t,disabled:a,invalid:n}){const o=/^#[0-9a-fA-F]{6}$/.test(r)?r:`#${r}`,c=/^#[0-9a-fA-F]{6}$/.test(o)?o:"#000000";return e.jsxs("div",{className:"ec-color",children:[e.jsx("input",{type:"color",className:"ec-color-swatch",value:c,disabled:a,onChange:l=>t(l.target.value)}),e.jsx(z,{className:"ec-input-narrow",value:r,disabled:a,invalid:n,onChange:l=>t(l.target.value)})]})}function Oe({param:r,value:t,onChange:a,disabled:n,dirty:o,saved:c,invalid:l,onReset:i,boost:u}){const{t:d,lang:m}=R(),x=L(r.label,m,r.key),w=L(r.description,m,""),y=r.config.default,h=i!==void 0&&y!==void 0&&!n&&t!==String(y);return e.jsxs("div",{className:S("ec-field",o&&"ec-field-dirty"),children:[e.jsxs("div",{className:"ec-field-label-col",children:[e.jsxs("span",{className:"ec-field-label",children:[x,w!==""&&e.jsx(xr,{content:w,children:e.jsx("span",{className:"ec-help",children:e.jsx(ar,{size:13})})}),r.inferred&&e.jsx(J,{variant:"muted",children:d("field.auto_detected")})]}),w!==""&&e.jsx("span",{className:"ec-field-desc ec-truncate",children:w}),e.jsx("span",{className:"ec-field-desc ec-muted",children:r.section?`${r.section} · ${r.key}`:r.key})]}),e.jsxs("div",{className:"ec-field-control",children:[u,e.jsx(Sr,{param:r,value:t,onChange:a,disabled:n,invalid:l}),c&&e.jsx("span",{className:"ec-field-saved","aria-hidden":!0,children:e.jsx(ue,{size:15})}),h&&e.jsx(pe,{label:d("field.reset_default"),className:"ec-reset",onClick:i,children:e.jsx(dr,{size:14})})]})]})}function D(r){return typeof r=="object"&&r!==null&&!Array.isArray(r)}function Me(r,t,a){const n=D(a.config)?a.config:{},o=n.default;return{key:r,section:t,display_type:typeof a.display_type=="string"?a.display_type:"text",config:n,label:D(a.label)?a.label:null,description:D(a.description)?a.description:null,value:o===void 0?"":String(o),inferred:!1}}function Ar(r){const t=[];if(!D(r))return{sectioned:!1,params:t};let a=!1;for(const[n,o]of Object.entries(r))if(D(o)&&"display_type"in o)t.push(Me(n,null,o));else if(D(o)){a=!0;for(const[c,l]of Object.entries(o))D(l)&&t.push(Me(c,n,l))}return{sectioned:a,params:t}}function Pr({files:r}){const{t,lang:a}=R(),[n,o]=f.useState({});return!Array.isArray(r)||r.length===0?e.jsx("div",{className:"ec-empty",children:t("admin.editor.preview_empty")}):e.jsx("div",{className:"ec-stack",children:r.map((c,l)=>{if(!D(c))return null;const{params:i}=Ar(c.parameters),u=D(c.label)?L(c.label,a,String(c.path??"")):String(c.path??`file ${l+1}`);return e.jsxs("div",{className:"ec-section-group",children:[e.jsx("div",{className:"ec-section-head",children:u}),e.jsxs("div",{className:"ec-section-body",children:[i.map(d=>{const m=`${l}:${d.section??""}:${d.key}`;return e.jsx(Oe,{param:d,value:n[m]??d.value,onChange:x=>o(w=>({...w,[m]:x}))},m)}),i.length===0&&e.jsx("div",{className:"ec-empty",children:t("admin.editor.no_params")})]})]},l)})})}function Fe(r,t){const a={};return r.trim()!==""&&(a.en=r.trim()),t.trim()!==""&&(a.fr=t.trim()),a}function Ie(r){try{return JSON.parse(r)}catch{return null}}function Le({initial:r,isNew:t}){const{t:a}=R(),n=V.useNavigate(),o=me(),c=wr(),l=kr(),[i,u]=f.useState(r),[d,m]=f.useState("edit"),[x,w]=f.useState([]),y=v=>u(P=>({...P,...v})),h=()=>{const v=Ie(i.filesJson);if(v===null){w([a("admin.editor.invalid_files_json")]);return}const P={id:i.id,version:i.version===""?"1.0.0":i.version,name:Fe(i.nameEn,i.nameFr),description:Fe(i.descEn,i.descFr),author:i.author===""?null:i.author,target_eggs:i.targetEggs,boost:{enabled:i.boostEnabled,parameter_blacklist:i.blacklist.split(",").map(I=>I.trim()).filter(I=>I!=="")},files:v};w([]),l.mutate({id:t?null:i.id,template:P},{onSuccess:()=>{o.success(a("admin.editor.saved")),n(`/plugins/${H}`)},onError:I=>{const Y=I;w(Y.messages??[Y.message??a("errors.generic")]),o.error(a("admin.editor.save_failed"))}})};return e.jsxs("div",{className:"ec-page",children:[e.jsxs("div",{className:"ec-between",children:[e.jsxs("div",{className:"ec-row",children:[e.jsxs($,{variant:"ghost",onClick:()=>n(`/plugins/${H}`),children:[e.jsx(er,{size:15})," ",a("common.back")]}),e.jsx("h1",{className:"ec-title",children:t?a("admin.editor.title_new"):i.id})]}),e.jsxs($,{loading:l.isPending,onClick:h,children:[e.jsx(Se,{size:15})," ",a("common.save")]})]}),x.length>0&&e.jsx(F,{children:e.jsx("ul",{className:"ec-error-list",children:x.map(v=>e.jsx("li",{children:v},v))})}),e.jsx(vr,{active:d,onChange:v=>m(v),tabs:[{id:"edit",label:a("admin.editor.tab_edit")},{id:"preview",label:a("admin.editor.tab_preview")}]}),d==="edit"?e.jsxs("div",{className:"ec-stack",children:[e.jsx(F,{children:e.jsxs("div",{className:"ec-stack",children:[e.jsxs("div",{className:"ec-field-group",children:[e.jsx("label",{children:a("admin.editor.id")}),e.jsx(z,{value:i.id,disabled:!t,placeholder:"minecraft-vanilla",onChange:v=>y({id:v.target.value})})]}),e.jsxs("div",{className:"ec-cols-2",children:[e.jsxs("div",{className:"ec-field-group",children:[e.jsx("label",{children:a("admin.editor.name_en")}),e.jsx(z,{value:i.nameEn,onChange:v=>y({nameEn:v.target.value})})]}),e.jsxs("div",{className:"ec-field-group",children:[e.jsx("label",{children:a("admin.editor.name_fr")}),e.jsx(z,{value:i.nameFr,onChange:v=>y({nameFr:v.target.value})})]})]}),e.jsxs("div",{className:"ec-cols-2",children:[e.jsxs("div",{className:"ec-field-group",children:[e.jsx("label",{children:a("admin.editor.desc_en")}),e.jsx(z,{value:i.descEn,onChange:v=>y({descEn:v.target.value})})]}),e.jsxs("div",{className:"ec-field-group",children:[e.jsx("label",{children:a("admin.editor.desc_fr")}),e.jsx(z,{value:i.descFr,onChange:v=>y({descFr:v.target.value})})]})]}),e.jsxs("div",{className:"ec-field-group",children:[e.jsx("label",{children:a("admin.editor.author")}),e.jsx(z,{value:i.author,onChange:v=>y({author:v.target.value})})]})]})}),e.jsx(F,{children:e.jsx(Er,{value:i.targetEggs,onChange:v=>y({targetEggs:v}),eggs:c.data??[],loading:c.isLoading})}),e.jsx(F,{children:e.jsxs("div",{className:"ec-stack",children:[e.jsxs("div",{className:"ec-between",children:[e.jsx("span",{className:"ec-field-label",children:a("admin.editor.boost_enabled")}),e.jsx(Pe,{checked:i.boostEnabled,onChange:v=>y({boostEnabled:v}),label:a("admin.editor.boost_enabled")})]}),i.boostEnabled&&e.jsxs("div",{className:"ec-field-group",children:[e.jsx("label",{children:a("admin.editor.boost_blacklist")}),e.jsx(z,{value:i.blacklist,placeholder:"server-port, rcon.port",onChange:v=>y({blacklist:v.target.value})}),e.jsx("span",{className:"ec-field-desc ec-muted",children:a("admin.editor.boost_blacklist_hint")})]})]})}),e.jsx(F,{children:e.jsxs("div",{className:"ec-field-group",children:[e.jsx("label",{children:a("admin.editor.files_json")}),e.jsx(fe,{className:"ec-mono",value:i.filesJson,spellCheck:!1,onChange:v=>y({filesJson:v.target.value})})]})})]}):e.jsx(Pr,{files:Ie(i.filesJson)})]})}const Or=`[
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
]`;function ge(r){return typeof r=="object"&&r!==null&&!Array.isArray(r)}function W(r,t=""){return typeof r=="string"?r:t}function $e(){return{id:"",version:"1.0.0",nameEn:"",nameFr:"",descEn:"",descFr:"",author:"",targetEggs:[],boostEnabled:!1,blacklist:"",filesJson:Or}}function Mr(r,t){if(t===null)return{...$e(),id:r};const a=ge(t.name)?t.name:{},n=ge(t.description)?t.description:{},o=ge(t.boost)?t.boost:{},c=Array.isArray(o.parameter_blacklist)?o.parameter_blacklist.map(String):[],l=Array.isArray(t.target_eggs)?t.target_eggs.filter(i=>typeof i=="number"):[];return{id:r,version:W(t.version,"1.0.0"),nameEn:W(a.en),nameFr:W(a.fr),descEn:W(n.en),descFr:W(n.fr),author:W(t.author),targetEggs:l,boostEnabled:o.enabled===!0,blacklist:c.join(", "),filesJson:JSON.stringify(t.files??[],null,2)}}function De(){var o;const{t:r}=R(),{templateId:t}=V.useParams(),a=t===void 0,n=jr(a?null:t??null);return a?e.jsx(Le,{initial:$e(),isNew:!0}):n.isError&&((o=n.error)==null?void 0:o.status)===403?e.jsx("div",{className:"ec-page",children:e.jsx(F,{children:e.jsx(Z,{children:r("admin.unauthorized")})})}):n.isLoading||n.data===void 0?e.jsx("div",{className:"ec-page",children:e.jsxs("div",{className:"ec-row ec-muted",children:[e.jsx(ne,{})," ",r("common.loading")]})}):e.jsx(Le,{initial:Mr(n.data.id,n.data.definition),isNew:!1},n.data.id)}function Fr({open:r,onClose:t,title:a,children:n,footer:o,size:c="md",closeLabel:l}){return f.useEffect(()=>{if(!r)return;const i=u=>{u.key==="Escape"&&t()};return window.addEventListener("keydown",i),()=>window.removeEventListener("keydown",i)},[r,t]),r?e.jsx("div",{className:"ec-scrim",onMouseDown:i=>{i.target===i.currentTarget&&t()},children:e.jsxs("div",{className:S("ec-dialog",c==="lg"&&"ec-dialog-lg"),role:"dialog","aria-modal":"true",children:[e.jsxs("div",{className:"ec-dialog-head",children:[e.jsx("p",{className:"ec-dialog-title ec-grow",children:a}),e.jsx(pe,{label:l,onClick:t,children:e.jsx(fr,{size:16})})]}),n,o!==void 0&&e.jsx("div",{className:"ec-dialog-foot",children:o})]})}):null}function Ir(){var y;const{t:r,lang:t}=R(),a=V.useNavigate(),n=me(),o=yr(),c=Nr(),l=_r(),[i,u]=f.useState(!1),[d,m]=f.useState("");if(o.isError&&((y=o.error)==null?void 0:y.status)===403)return e.jsx("div",{className:"ec-page",children:e.jsx(F,{children:e.jsx(Z,{children:r("admin.unauthorized")})})});const x=h=>{window.confirm(r("admin.list.confirm_delete",{id:h}))&&c.mutate(h,{onSuccess:()=>n.success(r("admin.list.deleted")),onError:()=>n.error(r("errors.generic"))})},w=()=>{l.mutate(d,{onSuccess:()=>{n.success(r("admin.list.imported")),u(!1),m("")},onError:h=>n.error(h.message??r("admin.list.import_failed"))})};return e.jsxs("div",{className:"ec-page",children:[e.jsxs("div",{className:"ec-between",children:[e.jsxs("div",{children:[e.jsx("h1",{className:"ec-title",children:r("admin.list.title")}),e.jsx("p",{className:"ec-subtitle",children:r("admin.list.subtitle")})]}),e.jsxs("div",{className:"ec-row",children:[e.jsxs($,{variant:"secondary",onClick:()=>u(!0),children:[e.jsx(pr,{size:15})," ",r("admin.list.import")]}),e.jsxs($,{onClick:()=>a(`/plugins/${H}/new`),children:[e.jsx(sr,{size:15})," ",r("admin.list.new")]})]})]}),o.isLoading?e.jsxs("div",{className:"ec-row ec-muted",children:[e.jsx(ne,{})," ",r("common.loading")]}):o.data&&o.data.length>0?e.jsx("div",{className:"ec-grid",children:o.data.map(h=>e.jsxs(F,{hover:!0,className:"ec-template-card",children:[e.jsxs("div",{className:"ec-between",children:[e.jsx("strong",{className:"ec-truncate",children:L(h.name,t,h.template_id)}),h.is_valid?e.jsxs(J,{variant:"success",children:["v",h.version]}):e.jsx(J,{variant:"warning",children:r("admin.list.invalid")})]}),e.jsx("span",{className:"ec-subtitle ec-truncate",children:h.template_id}),!h.is_valid&&h.last_error!==null&&e.jsx("span",{className:"ec-field-desc ec-truncate",children:h.last_error}),e.jsxs("div",{className:"ec-template-card-foot",children:[e.jsx(J,{variant:"muted",children:r("admin.list.files",{count:h.file_count})}),e.jsx(J,{variant:"muted",children:r("admin.list.eggs",{count:h.target_eggs.length})}),h.boost_enabled&&e.jsx(J,{variant:"accent",children:r("admin.list.boost")})]}),e.jsxs("div",{className:"ec-row",children:[e.jsxs($,{size:"sm",variant:"secondary",onClick:()=>a(`/plugins/${H}/${h.template_id}`),children:[e.jsx(cr,{size:13})," ",r("common.edit")]}),e.jsxs("a",{className:"ec-btn ec-btn-ghost ec-btn-sm",href:`${A}/admin/templates/${h.template_id}/export`,children:[e.jsx(or,{size:13})," ",r("common.export")]}),e.jsx(pe,{label:r("common.delete"),onClick:()=>x(h.template_id),children:e.jsx(mr,{size:14})})]})]},h.template_id))}):e.jsx(F,{children:e.jsx(Z,{children:r("admin.list.empty")})}),e.jsx(Fr,{open:i,onClose:()=>u(!1),closeLabel:r("common.close"),title:r("admin.list.import_title"),footer:e.jsxs(e.Fragment,{children:[e.jsx($,{variant:"ghost",onClick:()=>u(!1),children:r("common.cancel")}),e.jsx($,{loading:l.isPending,onClick:w,children:r("admin.list.import")})]}),children:e.jsx("div",{className:"ec-dialog-body",children:e.jsx(fe,{className:"ec-mono",value:d,placeholder:'{ "id": "minecraft-vanilla", ... }',onChange:h=>m(h.target.value)})})})]})}function Lr(){return e.jsx(ze,{children:e.jsx("div",{className:"ec-root",children:e.jsxs(V.Routes,{children:[e.jsx(V.Route,{path:"",element:e.jsx(Ir,{})}),e.jsx(V.Route,{path:"new",element:e.jsx(De,{})}),e.jsx(V.Route,{path:":templateId",element:e.jsx(De,{})})]})})})}const he="";function $r(r,t,a){return`${r}${he}${t??""}${he}${a}`}function Ye(r,t){return $r(r,t.section,t.key)}function Dr(r,t){return`${r}${he}${t}`}function Yr(r,t){const a=r.config;switch(r.display_type){case"number":case"slider":{if(t.trim()===""||!Number.isFinite(Number(t)))return"number";const n=Number(t);return a.min!==void 0&&n<a.min?"min":a.max!==void 0&&n>a.max?"max":!a.float&&t.includes(".")?"integer":null}case"select":{const n=(a.options??[]).map(o=>o.value);return n.length===0||n.includes(t)?null:"option"}case"multiselect":{const n=a.separator&&a.separator!==""?a.separator:",",o=(a.options??[]).map(c=>c.value);if(o.length===0)return null;for(const c of t.split(n).map(l=>l.trim()).filter(l=>l!==""))if(!o.includes(c))return"option";return null}case"boolean":{const n=a.true_value??"true",o=a.false_value??"false";return t===n||t===o?null:"boolean"}case"text":{if(a.max_length!==void 0&&t.length>a.max_length)return"length";if(a.regex!==void 0&&a.regex!=="")try{if(!new RegExp(a.regex).test(t))return"format"}catch{return null}return null}case"textarea":return a.max_length!==void 0&&t.length>a.max_length?"length":null;case"color":return/^#?[0-9a-fA-F]{6}$/.test(t)?null:"color";default:return null}}function Vr({title:r,storageKey:t,count:a,children:n}){const[o,c]=f.useState(()=>{try{return localStorage.getItem(t)!=="0"}catch{return!0}}),l=()=>{c(i=>{const u=!i;try{localStorage.setItem(t,u?"1":"0")}catch{}return u})};return e.jsxs("div",{className:S("ec-section-group",!o&&"ec-section-collapsed"),children:[e.jsxs("button",{type:"button",className:"ec-section-head",onClick:l,"aria-expanded":o,children:[e.jsx("span",{className:"ec-section-chevron",children:e.jsx(rr,{size:16})}),e.jsx("span",{children:r}),a!==void 0&&e.jsx("span",{className:"ec-section-count",children:a})]}),o&&e.jsx("div",{className:"ec-section-body",children:n})]})}function Jr(r){const t=new Map;for(const a of r){const n=t.get(a.section)??[];n.push(a),t.set(a.section,n)}return[...t.entries()]}function Kr({file:r,controller:t,serverId:a}){const{t:n,lang:o}=R(),c=L(r.label,o,r.path),l=t.search.trim().toLowerCase(),i=m=>{var x;return l===""?!0:L(m.label,o,m.key).toLowerCase().includes(l)||m.key.toLowerCase().includes(l)||(((x=m.section)==null?void 0:x.toLowerCase().includes(l))??!1)},u=r.parameters.filter(i),d=m=>{const x=Ye(r.id,m);return e.jsx(Oe,{param:m,value:t.getValue(x),dirty:t.isDirty(x),saved:t.isSaved(x),invalid:t.isInvalid(x),disabled:t.disabled,onChange:w=>t.onChange(x,m,w),onReset:()=>t.onReset(x,m)},x)};return e.jsxs("div",{className:"ec-stack",children:[e.jsxs("div",{className:"ec-between",children:[e.jsx("h3",{className:"ec-title",children:c}),!r.exists&&e.jsx(J,{variant:"warning",children:n("file.missing_badge")})]}),r.exists?u.length===0?e.jsx(F,{children:e.jsx(Z,{children:n("section.no_results")})}):r.sectioned?Jr(u).map(([m,x])=>e.jsx(Vr,{title:m??n("section.general"),storageKey:`ec:col:${a}:${r.id}:${m??""}`,count:x.length,children:x.map(d)},m??"_general")):e.jsx("div",{className:"ec-section-group",children:e.jsx("div",{className:"ec-section-body",children:u.map(d)})}):e.jsx(F,{children:e.jsx(Z,{children:n("file.missing",{path:r.path})})})]})}function Hr({saving:r,saved:t,onSave:a}){const{t:n}=R();return e.jsxs("div",{className:"ec-save-bar",children:[e.jsx("span",{className:"ec-save-bar-text",children:n(t?"save.saved":"save.unsaved")}),e.jsxs($,{onClick:a,loading:r,disabled:t,children:[t?e.jsx(ue,{size:15}):e.jsx(Se,{size:15}),n(t?"save.saved":"save.save")]})]})}function Wr(r){return T.useQuery({queryKey:["ec-config",r],staleTime:1/0,queryFn:()=>M(`${A}/servers/${r}/config`).then(t=>t.data)})}function Ur(r,t){return T.useQuery({queryKey:["ec-status",r],enabled:t,refetchInterval:a=>{var n;return((n=a.state.data)==null?void 0:n.state)==="offline"?!1:5e3},queryFn:()=>M(`${A}/servers/${r}/status`).then(a=>a.data)})}function Br(r){return T.useMutation({mutationFn:t=>M(`${A}/servers/${r}/config`,{method:"PUT",body:JSON.stringify({files:t})})})}function Gr(r){return T.useMutation({mutationFn:t=>M(`${A}/servers/${r}/power`,{method:"POST",body:JSON.stringify({signal:t})})})}function Xr({serverId:r,templates:t,disabled:a}){const{t:n,lang:o}=R(),c=me(),l=Br(r),{initial:i,index:u}=f.useMemo(()=>{const g={},b=new Map;for(const k of t)for(const C of k.files)for(const O of C.parameters){const K=Ye(C.id,O);g[K]=O.value,b.set(K,{param:O,fileId:C.id})}return{initial:g,index:b}},[t]),[d,m]=f.useState(i),[x,w]=f.useState(i),[y,h]=f.useState({}),[v,P]=f.useState(new Set),[I,Y]=f.useState(!1),[se,ve]=f.useState(""),U=f.useMemo(()=>Object.keys(d).filter(g=>d[g]!==x[g]),[d,x]),Q=U.length>0,ie=Object.values(y).some(Boolean),q=f.useCallback((g,b,k)=>{Y(!1),m(O=>({...O,[g]:k}));const C=Yr(b,k);h(O=>({...O,[g]:C!==null})),C!==null&&c.warning(n("validation.invalid_value",{param:L(b.label,o,b.key),type:n(`validation.type.${C}`)}))},[c,n,o]),ee=f.useCallback((g,b)=>{b.config.default!==void 0&&q(g,b,String(b.config.default))},[q]),re=f.useCallback(()=>{if(!Q||a||l.isPending)return;if(ie){c.error(n("save.fix_invalid"));return}const g=new Map;for(const b of U){const k=u.get(b);if(k===void 0)continue;const C=g.get(k.fileId)??[];C.push({key:k.param.key,section:k.param.section,value:d[b]??""}),g.set(k.fileId,C)}l.mutate([...g.entries()].map(([b,k])=>({id:b,values:k})),{onSuccess:()=>{w({...d}),P(new Set(U)),Y(!0),h({}),window.setTimeout(()=>{Y(!1),P(new Set)},2e3),c.success(n("save.saved"))},onError:b=>{const k=b;if(k.status===422&&k.fields){const C={};for(const[O,K]of Object.entries(k.fields))for(const ce of Object.keys(K))C[Dr(O,ce)]=!0;h(C)}c.error(n("save.error"))}})},[Q,a,ie,U,u,d,l,c,n]);f.useEffect(()=>{const g=b=>{(b.metaKey||b.ctrlKey)&&b.key.toLowerCase()==="s"&&(b.preventDefault(),re())};return window.addEventListener("keydown",g),()=>window.removeEventListener("keydown",g)},[re]);const xe={getValue:g=>d[g]??"",isDirty:g=>d[g]!==x[g],isSaved:g=>v.has(g),isInvalid:g=>y[g]??!1,disabled:a,search:se,onChange:q,onReset:ee},te=t.flatMap(g=>g.files.map(b=>({key:`${g.id}:${b.id}`,file:b})));return e.jsxs("div",{className:"ec-stack",children:[e.jsx("div",{className:"ec-between",children:e.jsxs("div",{className:"ec-row",children:[e.jsx("span",{className:"ec-icon-box",children:e.jsx(ur,{size:18})}),e.jsxs("div",{children:[e.jsx("h2",{className:"ec-title",children:n("section.title")}),e.jsx("p",{className:"ec-subtitle",children:n("section.subtitle")})]})]})}),e.jsxs("div",{className:"ec-search",children:[e.jsx("span",{className:"ec-search-icon",children:e.jsx(Ce,{size:14})}),e.jsx(z,{value:se,placeholder:n("section.search"),onChange:g=>ve(g.target.value)})]}),te.map(({key:g,file:b})=>e.jsx(Kr,{file:b,controller:xe,serverId:r},g)),(Q||I)&&!a&&e.jsx(Hr,{saving:l.isPending,saved:I,onSave:re})]})}function Zr({state:r,onStop:t,stopping:a}){const{t:n}=R();return e.jsx("div",{className:"ec-overlay",children:e.jsxs("div",{className:"ec-overlay-card",children:[e.jsx("span",{className:"ec-icon-box",children:e.jsx(Te,{size:20})}),e.jsx("p",{className:"ec-title",children:n("overlay.running_title")}),e.jsx("p",{className:"ec-subtitle",children:n("overlay.running_desc")}),e.jsxs($,{onClick:t,loading:a||r==="stopping",children:[e.jsx(lr,{size:15})," ",n("overlay.stop_button")]})]})})}function Qr({serverId:r}){var u,d;const{t}=R(),a=Wr(r),n=(((u=a.data)==null?void 0:u.templates.length)??0)>0,o=Ur(r,n),c=Gr(r);if(a.isLoading)return e.jsxs("div",{className:"ec-card ec-row ec-muted",children:[e.jsx(ne,{})," ",t("common.loading")]});if(a.isError||!a.data||!n)return null;const l=((d=o.data)==null?void 0:d.state)??"offline",i=o.isSuccess&&l!=="offline";return e.jsxs("div",{className:"ec-relative",children:[e.jsx(Xr,{serverId:r,templates:a.data.templates,disabled:i},r),i&&e.jsx(Zr,{state:l,stopping:c.isPending,onStop:()=>c.mutate("stop")})]})}function qr({serverId:r}){return e.jsx(ze,{children:e.jsx("div",{className:"ec-root",children:e.jsx(Qr,{serverId:r})})})}const et=`
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
`,rt=`
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

@keyframes ec-spin { to { transform: rotate(360deg); } }
@keyframes ec-fade-in { from { opacity: 0; } to { opacity: 1; } }
@keyframes ec-pop-in { from { opacity: 0; transform: translateY(6px) scale(0.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
@keyframes ec-slide-up { from { opacity: 0; transform: translate(-50%, 1rem); } to { opacity: 1; transform: translate(-50%, 0); } }
`,tt=`
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
`,at=`
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
`,Ve="easy-config-styles";function nt(){if(typeof document>"u"||document.getElementById(Ve))return;const r=document.createElement("style");r.id=Ve,r.textContent=[rt,tt,at,et].join(`
`),document.head.appendChild(r)}nt(),Ae.register("easy-configuration",Lr),Ae.registerServerHomeSection("easy-config",qr)})(window.__PEREGRINE_SHARED__.React,window.__PEREGRINE_SHARED__.ReactRouterDom,window.__PEREGRINE_SHARED__,window.__PEREGRINE_SHARED__.ReactQuery);
