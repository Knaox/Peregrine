(function(h,X,et,T){"use strict";var fe={exports:{}},le={};/**
 * @license React
 * react-jsx-runtime.production.js
 *
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */var Me;function tt(){if(Me)return le;Me=1;var t=Symbol.for("react.transitional.element"),r=Symbol.for("react.fragment");function s(n,a,l){var i=null;if(l!==void 0&&(i=""+l),a.key!==void 0&&(i=""+a.key),"key"in a){l={};for(var o in a)o!=="key"&&(l[o]=a[o])}else l=a;return a=l.ref,{$$typeof:t,type:n,key:i,ref:a!==void 0?a:null,props:l}}return le.Fragment=r,le.jsx=s,le.jsxs=s,le}var de={};/**
 * @license React
 * react-jsx-runtime.development.js
 *
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */var Ae;function rt(){return Ae||(Ae=1,process.env.NODE_ENV!=="production"&&(function(){function t(d){if(d==null)return null;if(typeof d=="function")return d.$$typeof===ce?null:d.displayName||d.name||null;if(typeof d=="string")return d;switch(d){case F:return"Fragment";case $:return"Profiler";case B:return"StrictMode";case L:return"Suspense";case C:return"SuspenseList";case me:return"Activity"}if(typeof d=="object")switch(typeof d.tag=="number"&&console.error("Received an unexpected object in getComponentNameFromType(). This is likely a bug in React. Please file an issue."),d.$$typeof){case b:return"Portal";case ee:return d.displayName||"Context";case q:return(d._context.displayName||"Context")+".Consumer";case N:var y=d.render;return d=d.displayName,d||(d=y.displayName||y.name||"",d=d!==""?"ForwardRef("+d+")":"ForwardRef"),d;case D:return y=d.displayName||null,y!==null?y:t(d.type)||"Memo";case V:y=d._payload,d=d._init;try{return t(d(y))}catch{}}return null}function r(d){return""+d}function s(d){try{r(d);var y=!1}catch{y=!0}if(y){y=console;var p=y.error,f=typeof Symbol=="function"&&Symbol.toStringTag&&d[Symbol.toStringTag]||d.constructor.name||"Object";return p.call(y,"The provided key is an unsupported type %s. This value must be coerced to a string before using it here.",f),r(d)}}function n(d){if(d===F)return"<>";if(typeof d=="object"&&d!==null&&d.$$typeof===V)return"<...>";try{var y=t(d);return y?"<"+y+">":"<...>"}catch{return"<...>"}}function a(){var d=H.A;return d===null?null:d.getOwner()}function l(){return Error("react-stack-top-frame")}function i(d){if(re.call(d,"key")){var y=Object.getOwnPropertyDescriptor(d,"key").get;if(y&&y.isReactWarning)return!1}return d.key!==void 0}function o(d,y){function p(){Z||(Z=!0,console.error("%s: `key` is not a prop. Trying to access it will result in `undefined` being returned. If you need to access the same value within the child component, you should pass it as a different prop. (https://react.dev/link/special-props)",y))}p.isReactWarning=!0,Object.defineProperty(d,"key",{get:p,configurable:!0})}function u(){var d=t(this.type);return _[d]||(_[d]=!0,console.error("Accessing element.ref was removed in React 19. ref is now a regular prop. It will be removed from the JSX Element type in a future release.")),d=this.props.ref,d!==void 0?d:null}function c(d,y,p,f,w,S){var v=p.ref;return d={$$typeof:g,type:d,key:y,props:p,_owner:f},(v!==void 0?v:null)!==null?Object.defineProperty(d,"ref",{enumerable:!1,get:u}):Object.defineProperty(d,"ref",{enumerable:!1,value:null}),d._store={},Object.defineProperty(d._store,"validated",{configurable:!1,enumerable:!1,writable:!0,value:0}),Object.defineProperty(d,"_debugInfo",{configurable:!1,enumerable:!1,writable:!0,value:null}),Object.defineProperty(d,"_debugStack",{configurable:!1,enumerable:!1,writable:!0,value:w}),Object.defineProperty(d,"_debugTask",{configurable:!1,enumerable:!1,writable:!0,value:S}),Object.freeze&&(Object.freeze(d.props),Object.freeze(d)),d}function m(d,y,p,f,w,S){var v=y.children;if(v!==void 0)if(f)if(pe(v)){for(f=0;f<v.length;f++)x(v[f]);Object.freeze&&Object.freeze(v)}else console.error("React.jsx: Static children should always be an array. You are likely explicitly calling React.jsxs or React.jsxDEV. Use the Babel transform instead.");else x(v);if(re.call(y,"key")){v=t(d);var U=Object.keys(y).filter(function(Er){return Er!=="key"});f=0<U.length?"{key: someKey, "+U.join(": ..., ")+": ...}":"{key: someKey}",ie[v+f]||(U=0<U.length?"{"+U.join(": ..., ")+": ...}":"{}",console.error(`A props object containing a "key" prop is being spread into JSX:
  let props = %s;
  <%s {...props} />
React keys must be passed directly to JSX without using spread:
  let props = %s;
  <%s key={someKey} {...props} />`,f,v,U,v),ie[v+f]=!0)}if(v=null,p!==void 0&&(s(p),v=""+p),i(y)&&(s(y.key),v=""+y.key),"key"in y){p={};for(var he in y)he!=="key"&&(p[he]=y[he])}else p=y;return v&&o(p,typeof d=="function"?d.displayName||d.name||"Unknown":d),c(d,v,p,a(),w,S)}function x(d){j(d)?d._store&&(d._store.validated=1):typeof d=="object"&&d!==null&&d.$$typeof===V&&(d._payload.status==="fulfilled"?j(d._payload.value)&&d._payload.value._store&&(d._payload.value._store.validated=1):d._store&&(d._store.validated=1))}function j(d){return typeof d=="object"&&d!==null&&d.$$typeof===g}var k=h,g=Symbol.for("react.transitional.element"),b=Symbol.for("react.portal"),F=Symbol.for("react.fragment"),B=Symbol.for("react.strict_mode"),$=Symbol.for("react.profiler"),q=Symbol.for("react.consumer"),ee=Symbol.for("react.context"),N=Symbol.for("react.forward_ref"),L=Symbol.for("react.suspense"),C=Symbol.for("react.suspense_list"),D=Symbol.for("react.memo"),V=Symbol.for("react.lazy"),me=Symbol.for("react.activity"),ce=Symbol.for("react.client.reference"),H=k.__CLIENT_INTERNALS_DO_NOT_USE_OR_WARN_USERS_THEY_CANNOT_UPGRADE,re=Object.prototype.hasOwnProperty,pe=Array.isArray,G=console.createTask?console.createTask:function(){return null};k={react_stack_bottom_frame:function(d){return d()}};var Z,_={},K=k.react_stack_bottom_frame.bind(k,l)(),W=G(n(l)),ie={};de.Fragment=F,de.jsx=function(d,y,p){var f=1e4>H.recentlyCreatedOwnerStacks++;return m(d,y,p,!1,f?Error("react-stack-top-frame"):K,f?G(n(d)):W)},de.jsxs=function(d,y,p){var f=1e4>H.recentlyCreatedOwnerStacks++;return m(d,y,p,!0,f?Error("react-stack-top-frame"):K,f?G(n(d)):W)}})()),de}var Pe;function st(){return Pe||(Pe=1,process.env.NODE_ENV==="production"?fe.exports=tt():fe.exports=rt()),fe.exports}var e=st();function Oe(t){var r,s,n="";if(typeof t=="string"||typeof t=="number")n+=t;else if(typeof t=="object")if(Array.isArray(t)){var a=t.length;for(r=0;r<a;r++)t[r]&&(s=Oe(t[r]))&&(n&&(n+=" "),n+=s)}else for(s in t)t[s]&&(n&&(n+=" "),n+=s);return n}function A(){for(var t,r,s=0,n="",a=arguments.length;s<a;s++)(t=arguments[s])&&(r=Oe(t))&&(n&&(n+=" "),n+=r);return n}/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Fe=(...t)=>t.filter((r,s,n)=>!!r&&r.trim()!==""&&n.indexOf(r)===s).join(" ").trim();/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const nt=t=>t.replace(/([a-z0-9])([A-Z])/g,"$1-$2").toLowerCase();/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const at=t=>t.replace(/^([A-Z])|[\s-_]+(\w)/g,(r,s,n)=>n?n.toUpperCase():s.toLowerCase());/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Le=t=>{const r=at(t);return r.charAt(0).toUpperCase()+r.slice(1)};/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */var ke={xmlns:"http://www.w3.org/2000/svg",width:24,height:24,viewBox:"0 0 24 24",fill:"none",stroke:"currentColor",strokeWidth:2,strokeLinecap:"round",strokeLinejoin:"round"};/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ot=t=>{for(const r in t)if(r.startsWith("aria-")||r==="role"||r==="title")return!0;return!1},ct=h.createContext({}),it=()=>h.useContext(ct),lt=h.forwardRef(({color:t,size:r,strokeWidth:s,absoluteStrokeWidth:n,className:a="",children:l,iconNode:i,...o},u)=>{const{size:c=24,strokeWidth:m=2,absoluteStrokeWidth:x=!1,color:j="currentColor",className:k=""}=it()??{},g=n??x?Number(s??m)*24/Number(r??c):s??m;return h.createElement("svg",{ref:u,...ke,width:r??c??ke.width,height:r??c??ke.height,stroke:t??j,strokeWidth:g,className:Fe("lucide",k,a),...!l&&!ot(o)&&{"aria-hidden":"true"},...o},[...i.map(([b,F])=>h.createElement(b,F)),...Array.isArray(l)?l:[l]])});/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const E=(t,r)=>{const s=h.forwardRef(({className:n,...a},l)=>h.createElement(lt,{ref:l,iconNode:r,className:Fe(`lucide-${nt(Le(t))}`,`lucide-${t}`,n),...a}));return s.displayName=Le(t),s};/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const dt=E("arrow-left",[["path",{d:"m12 19-7-7 7-7",key:"1l729n"}],["path",{d:"M19 12H5",key:"x3x0zl"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ge=E("check",[["path",{d:"M20 6 9 17l-5-5",key:"1gmf2c"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ut=E("chevron-down",[["path",{d:"m6 9 6 6 6-6",key:"qrunsl"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const mt=E("circle-check",[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}],["path",{d:"m9 12 2 2 4-4",key:"dzmm74"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const pt=E("circle-question-mark",[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}],["path",{d:"M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3",key:"1u773s"}],["path",{d:"M12 17h.01",key:"p32p05"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ht=E("circle-x",[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}],["path",{d:"m15 9-6 6",key:"1uzhvr"}],["path",{d:"m9 9 6 6",key:"z0biqf"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const ft=E("copy",[["rect",{width:"14",height:"14",x:"8",y:"8",rx:"2",ry:"2",key:"17jyea"}],["path",{d:"M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2",key:"zix9uf"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const gt=E("download",[["path",{d:"M12 15V3",key:"m9g1x1"}],["path",{d:"M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4",key:"ih7n3h"}],["path",{d:"m7 10 5 5 5-5",key:"brsn70"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const xt=E("file-plus-corner",[["path",{d:"M11.35 22H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.706.706l3.588 3.588A2.4 2.4 0 0 1 20 8v5.35",key:"17jvcc"}],["path",{d:"M14 2v5a1 1 0 0 0 1 1h5",key:"wfsgrz"}],["path",{d:"M14 19h6",key:"bvotb8"}],["path",{d:"M17 16v6",key:"18yu1i"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const vt=E("history",[["path",{d:"M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8",key:"1357e3"}],["path",{d:"M3 3v5h5",key:"1xhq8a"}],["path",{d:"M12 7v5l4 2",key:"1fdv2h"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const bt=E("info",[["circle",{cx:"12",cy:"12",r:"10",key:"1mglay"}],["path",{d:"M12 16v-4",key:"1dtifu"}],["path",{d:"M12 8h.01",key:"e9boi3"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const yt=E("pencil",[["path",{d:"M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z",key:"1a8usu"}],["path",{d:"m15 5 4 4",key:"1mk7zo"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const jt=E("plus",[["path",{d:"M5 12h14",key:"1ays0h"}],["path",{d:"M12 5v14",key:"s699le"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const kt=E("power",[["path",{d:"M12 2v10",key:"mnfbl"}],["path",{d:"M18.4 6.6a9 9 0 1 1-12.77.04",key:"obofu9"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const wt=E("rotate-ccw",[["path",{d:"M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8",key:"1357e3"}],["path",{d:"M3 3v5h5",key:"1xhq8a"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const De=E("save",[["path",{d:"M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z",key:"1c8476"}],["path",{d:"M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7",key:"1ydtos"}],["path",{d:"M7 3v4a1 1 0 0 0 1 1h7",key:"t51u73"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const we=E("search",[["path",{d:"m21 21-4.34-4.34",key:"14j7rj"}],["circle",{cx:"11",cy:"11",r:"8",key:"4ej97u"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const _t=E("sliders-horizontal",[["path",{d:"M10 5H3",key:"1qgfaw"}],["path",{d:"M12 19H3",key:"yhmn1j"}],["path",{d:"M14 3v4",key:"1sua03"}],["path",{d:"M16 17v4",key:"1q0r14"}],["path",{d:"M21 12h-9",key:"1o4lsq"}],["path",{d:"M21 19h-5",key:"1rlt1p"}],["path",{d:"M21 5h-7",key:"1oszz2"}],["path",{d:"M8 10v4",key:"tgpxqk"}],["path",{d:"M8 12H3",key:"a7s4jb"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Nt=E("trash-2",[["path",{d:"M10 11v6",key:"nco0om"}],["path",{d:"M14 11v6",key:"outv1u"}],["path",{d:"M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6",key:"miytrc"}],["path",{d:"M3 6h18",key:"d0wm0j"}],["path",{d:"M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2",key:"e791ji"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const _e=E("triangle-alert",[["path",{d:"m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3",key:"wmoenq"}],["path",{d:"M12 9v4",key:"juzpu7"}],["path",{d:"M12 17h.01",key:"p32p05"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const St=E("upload",[["path",{d:"M12 3v12",key:"1x0j5s"}],["path",{d:"m17 8-5-5-5 5",key:"7q97r8"}],["path",{d:"M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4",key:"ih7n3h"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Ie=E("x",[["path",{d:"M18 6 6 18",key:"1bl5f8"}],["path",{d:"m6 6 12 12",key:"d8bk6v"}]]);/**
 * @license lucide-react v1.16.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const $e=E("zap",[["path",{d:"M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z",key:"1xq2db"}]]),Be=h.createContext(null);function ue(){const t=h.useContext(Be);if(t===null)throw new Error("useToast must be used within a ToastProvider");return t}function Ke({children:t}){const[r,s]=h.useState([]),n=h.useRef(0),a=h.useCallback(o=>{s(u=>u.filter(c=>c.id!==o))},[]),l=h.useCallback((o,u="info")=>{n.current+=1;const c=n.current;s(m=>[...m,{id:c,variant:u,message:o}]),window.setTimeout(()=>a(c),4500)},[a]),i=h.useMemo(()=>({show:l,success:o=>l(o,"success"),error:o=>l(o,"error"),warning:o=>l(o,"warning")}),[l]);return e.jsxs(Be.Provider,{value:i,children:[t,r.length>0&&e.jsx("div",{className:"ec-toast-host",children:r.map(o=>e.jsxs("div",{className:A("ec-toast",`ec-toast-${o.variant}`),role:"status",onClick:()=>a(o.id),children:[e.jsx("span",{className:"ec-toast-icon",children:e.jsx(Ct,{variant:o.variant})}),e.jsx("span",{children:o.message})]},o.id))})]})}function Ct({variant:t}){return t==="success"?e.jsx(mt,{size:16}):t==="error"?e.jsx(ht,{size:16}):t==="warning"?e.jsx(_e,{size:16}):e.jsx(bt,{size:16})}const Ye=window.__PEREGRINE_PLUGINS__,Ne="easy-configuration",M=`/api/plugins/${Ne}`,xe=`/plugins/${Ne}/manage`;function Et(){var t;return((t=document.querySelector('meta[name="csrf-token"]'))==null?void 0:t.getAttribute("content"))??""}async function P(t,r={}){const s=await fetch(t,{...r,credentials:"same-origin",headers:{"Content-Type":"application/json",Accept:"application/json","X-CSRF-TOKEN":Et(),...r.headers}});if(!s.ok){const a=(await s.json().catch(()=>({}))).error;throw{status:s.status,code:a==null?void 0:a.code,message:a==null?void 0:a.message,messages:a==null?void 0:a.messages,fields:a==null?void 0:a.fields}}if(s.status!==204)return await s.json()}function z(){const{t,i18n:r}=et.useTranslation(Ne);return{t,lang:(r.language??"en").slice(0,2)}}function Y(t,r,s=""){return t?t[r]??t.en??t.fr??Object.values(t)[0]??s:s}function se({size:t}){return e.jsx("span",{className:A("ec-spinner",t==="lg"&&"ec-spinner-lg"),"aria-hidden":!0})}function J({children:t,className:r,hover:s}){return e.jsx("div",{className:A("ec-card",s&&"ec-card-hover",r),children:t})}function I({variant:t="muted",children:r}){return e.jsx("span",{className:A("ec-badge",`ec-badge-${t}`),children:r})}function te({children:t}){return e.jsx("div",{className:"ec-empty",children:t})}function Tt({tabs:t,active:r,onChange:s}){return e.jsx("div",{className:"ec-tabs",role:"tablist",children:t.map(n=>e.jsx("button",{type:"button",role:"tab","aria-selected":n.id===r,className:A("ec-tab",n.id===r&&"ec-tab-active"),onClick:()=>s(n.id),children:n.label},n.id))})}function Se({content:t,children:r}){return e.jsxs("span",{className:"ec-tooltip",tabIndex:0,children:[r,e.jsx("span",{role:"tooltip",className:"ec-tooltip-pop",children:t})]})}function R({variant:t="primary",size:r="md",loading:s=!1,disabled:n,type:a="button",className:l,children:i,...o}){return e.jsxs("button",{...o,type:a,disabled:n||s,className:A("ec-btn",`ec-btn-${t}`,r==="sm"&&"ec-btn-sm",l),children:[s&&e.jsx("span",{className:"ec-spinner","aria-hidden":!0}),i]})}function ve({label:t,type:r="button",className:s,children:n,...a}){return e.jsx("button",{...a,type:r,"aria-label":t,title:t,className:A("ec-btn","ec-btn-icon",s),children:n})}function O({invalid:t,className:r,...s}){return e.jsx("input",{...s,className:A("ec-input",t&&"ec-input-invalid",r)})}function Ce({invalid:t,className:r,...s}){return e.jsx("textarea",{...s,className:A("ec-textarea",t&&"ec-input-invalid",r)})}function Rt({value:t,onChange:r,children:s,className:n,disabled:a,invalid:l}){return e.jsx("select",{className:A("ec-select",l&&"ec-input-invalid",n),value:t,disabled:a,onChange:i=>r(i.target.value),children:s})}function be({checked:t,onChange:r,disabled:s,label:n}){return e.jsx("button",{type:"button",role:"switch","aria-checked":t,"aria-label":n,disabled:s,className:A("ec-toggle",t&&"ec-toggle-on"),onClick:()=>r(!t),children:e.jsx("span",{className:"ec-toggle-knob"})})}const ye=["ec-admin-templates"];function zt(){return T.useQuery({queryKey:ye,queryFn:()=>P(`${M}/admin/templates`).then(t=>t.data)})}function Mt(t){return T.useQuery({queryKey:["ec-admin-template",t],enabled:t!==null,queryFn:()=>P(`${M}/admin/templates/${t??""}`).then(r=>r.data)})}function At(){return T.useQuery({queryKey:["ec-admin-eggs"],staleTime:5*6e4,queryFn:()=>P(`${M}/admin/eggs`).then(t=>t.data)})}function Pt(){const t=T.useQueryClient();return T.useMutation({mutationFn:({id:r,template:s})=>r!==null?P(`${M}/admin/templates/${r}`,{method:"PUT",body:JSON.stringify({template:s})}):P(`${M}/admin/templates`,{method:"POST",body:JSON.stringify({template:s})}),onSuccess:()=>t.invalidateQueries({queryKey:ye})})}function Ot(){const t=T.useQueryClient();return T.useMutation({mutationFn:r=>P(`${M}/admin/templates/import`,{method:"POST",body:JSON.stringify({content:r})}),onSuccess:()=>t.invalidateQueries({queryKey:ye})})}function Ft(){const t=T.useQueryClient();return T.useMutation({mutationFn:r=>P(`${M}/admin/templates/${r}`,{method:"DELETE"}),onSuccess:()=>t.invalidateQueries({queryKey:ye})})}function Lt({value:t,onChange:r,eggs:s,loading:n}){const{t:a}=z(),[l,i]=h.useState(""),o=s.filter(c=>c.name.toLowerCase().includes(l.trim().toLowerCase())),u=c=>{r(t.includes(c)?t.filter(m=>m!==c):[...t,c])};return e.jsxs("div",{className:"ec-field-group",children:[e.jsx("label",{children:a("admin.editor.target_eggs")}),e.jsxs("div",{className:"ec-search",children:[e.jsx("span",{className:"ec-search-icon",children:e.jsx(we,{size:14})}),e.jsx(O,{value:l,placeholder:a("admin.editor.search_eggs"),onChange:c=>i(c.target.value)})]}),n?e.jsxs("div",{className:"ec-row ec-muted",children:[e.jsx(se,{})," ",a("common.loading")]}):e.jsxs("div",{className:"ec-egg-list",children:[o.map(c=>{const m=t.includes(c.id);return e.jsxs("button",{type:"button",className:A("ec-server-row",m&&"ec-server-row-on"),onClick:()=>u(c.id),children:[c.banner_image?e.jsx("img",{className:"ec-server-thumb",src:c.banner_image,alt:""}):e.jsx("span",{className:"ec-server-thumb"}),e.jsx("span",{className:"ec-grow ec-truncate",children:c.name}),e.jsxs("span",{className:"ec-muted",children:["#",c.id]}),m&&e.jsx(ge,{size:16})]},c.id)}),o.length===0&&e.jsx("div",{className:"ec-empty",children:a("admin.editor.no_eggs")})]})]})}function Dt({param:t,value:r,onChange:s,disabled:n,invalid:a}){const{lang:l}=z(),i=t.config;switch(t.display_type){case"boolean":return e.jsx(It,{config:i,value:r,onChange:s,disabled:n});case"slider":return e.jsx($t,{config:i,value:r,onChange:s,disabled:n,invalid:a});case"number":return e.jsx(O,{className:"ec-input-narrow",type:"number",min:i.min,max:i.max,step:i.step??(i.float?"any":1),value:r,disabled:n,invalid:a,onChange:o=>s(o.target.value)});case"select":return e.jsx(Rt,{value:r,disabled:n,invalid:a,onChange:s,children:(i.options??[]).map(o=>e.jsx("option",{value:o.value,children:Y(o.label,l,o.value)},o.value))});case"multiselect":return e.jsx(Bt,{config:i,value:r,onChange:s,disabled:n});case"textarea":return e.jsx(Ce,{value:r,disabled:n,invalid:a,maxLength:i.max_length,onChange:o=>s(o.target.value)});case"color":return e.jsx(Kt,{value:r,onChange:s,disabled:n,invalid:a});default:return e.jsx(O,{value:r,disabled:n,invalid:a,maxLength:i.max_length,onChange:o=>s(o.target.value)})}}function It({config:t,value:r,onChange:s,disabled:n}){const a=t.true_value??"true",l=t.false_value??"false";return e.jsx(be,{checked:r===a,disabled:n,onChange:i=>s(i?a:l)})}function $t({config:t,value:r,onChange:s,disabled:n,invalid:a}){const l=t.min??0,i=t.max??100,o=t.step??1;return e.jsxs("div",{className:"ec-slider-wrap",children:[e.jsx("input",{type:"range",className:"ec-slider",min:l,max:i,step:o,value:r,disabled:n,onChange:u=>s(u.target.value)}),e.jsx(O,{className:"ec-slider-number",type:"number",min:l,max:i,step:o,value:r,disabled:n,invalid:a,onChange:u=>s(u.target.value)})]})}function Bt({config:t,value:r,onChange:s,disabled:n}){const{lang:a}=z(),l=t.separator&&t.separator!==""?t.separator:",",i=r.split(l).map(u=>u.trim()).filter(u=>u!==""),o=u=>{const c=i.includes(u)?i.filter(m=>m!==u):[...i,u];s(c.join(l))};return e.jsx("div",{className:"ec-chips",children:(t.options??[]).map(u=>e.jsx("button",{type:"button",disabled:n,className:A("ec-chip",i.includes(u.value)&&"ec-chip-on"),onClick:()=>o(u.value),children:Y(u.label,a,u.value)},u.value))})}function Kt({value:t,onChange:r,disabled:s,invalid:n}){const a=/^#[0-9a-fA-F]{6}$/.test(t)?t:`#${t}`,l=/^#[0-9a-fA-F]{6}$/.test(a)?a:"#000000";return e.jsxs("div",{className:"ec-color",children:[e.jsx("input",{type:"color",className:"ec-color-swatch",value:l,disabled:s,onChange:i=>r(i.target.value)}),e.jsx(O,{className:"ec-input-narrow",value:t,disabled:s,invalid:n,onChange:i=>r(i.target.value)})]})}function Je({param:t,value:r,onChange:s,disabled:n,dirty:a,saved:l,invalid:i,onReset:o,boost:u}){const{t:c,lang:m}=z(),x=Y(t.label,m,t.key),j=Y(t.description,m,""),k=t.config.default,g=o!==void 0&&k!==void 0&&!n&&r!==String(k);return e.jsxs("div",{className:A("ec-field",a&&"ec-field-dirty"),children:[e.jsxs("div",{className:"ec-field-label-col",children:[e.jsxs("span",{className:"ec-field-label",children:[x,j!==""&&e.jsx(Se,{content:j,children:e.jsx("span",{className:"ec-help",children:e.jsx(pt,{size:13})})}),t.inferred&&e.jsx(I,{variant:"muted",children:c("field.auto_detected")})]}),j!==""&&e.jsx("span",{className:"ec-field-desc ec-truncate",children:j}),e.jsx("span",{className:"ec-field-desc ec-muted",children:t.section?`${t.section} · ${t.key}`:t.key})]}),e.jsxs("div",{className:"ec-field-control",children:[u,e.jsx(Dt,{param:t,value:r,onChange:s,disabled:n,invalid:i}),l&&e.jsx("span",{className:"ec-field-saved","aria-hidden":!0,children:e.jsx(ge,{size:15})}),g&&e.jsx(ve,{label:c("field.reset_default"),className:"ec-reset",onClick:o,children:e.jsx(wt,{size:14})})]})]})}function Q(t){return typeof t=="object"&&t!==null&&!Array.isArray(t)}function Ve(t,r,s){const n=Q(s.config)?s.config:{},a=n.default;return{key:t,section:r,display_type:typeof s.display_type=="string"?s.display_type:"text",config:n,label:Q(s.label)?s.label:null,description:Q(s.description)?s.description:null,value:a===void 0?"":String(a),inferred:!1}}function Yt(t){const r=[];if(!Q(t))return{sectioned:!1,params:r};let s=!1;for(const[n,a]of Object.entries(t))if(Q(a)&&"display_type"in a)r.push(Ve(n,null,a));else if(Q(a)){s=!0;for(const[l,i]of Object.entries(a))Q(i)&&r.push(Ve(l,n,i))}return{sectioned:s,params:r}}function Jt({files:t}){const{t:r,lang:s}=z(),[n,a]=h.useState({});return!Array.isArray(t)||t.length===0?e.jsx("div",{className:"ec-empty",children:r("admin.editor.preview_empty")}):e.jsx("div",{className:"ec-stack",children:t.map((l,i)=>{if(!Q(l))return null;const{params:o}=Yt(l.parameters),u=Q(l.label)?Y(l.label,s,String(l.path??"")):String(l.path??`file ${i+1}`);return e.jsxs("div",{className:"ec-section-group",children:[e.jsx("div",{className:"ec-section-head",children:u}),e.jsxs("div",{className:"ec-section-body",children:[o.map(c=>{const m=`${i}:${c.section??""}:${c.key}`;return e.jsx(Je,{param:c,value:n[m]??c.value,onChange:x=>a(j=>({...j,[m]:x}))},m)}),o.length===0&&e.jsx("div",{className:"ec-empty",children:r("admin.editor.no_params")})]})]},i)})})}function He(t,r){const s={};return t.trim()!==""&&(s.en=t.trim()),r.trim()!==""&&(s.fr=r.trim()),s}function We(t){try{return JSON.parse(t)}catch{return null}}function Ue({initial:t,isNew:r}){const{t:s}=z(),n=X.useNavigate(),a=ue(),l=At(),i=Pt(),[o,u]=h.useState(t),[c,m]=h.useState("edit"),[x,j]=h.useState([]),k=b=>u(F=>({...F,...b})),g=()=>{const b=We(o.filesJson);if(b===null){j([s("admin.editor.invalid_files_json")]);return}const F={id:o.id,version:o.version===""?"1.0.0":o.version,name:He(o.nameEn,o.nameFr),description:He(o.descEn,o.descFr),author:o.author===""?null:o.author,target_eggs:o.targetEggs,boost:{enabled:o.boostEnabled,parameter_blacklist:o.blacklist.split(",").map(B=>B.trim()).filter(B=>B!=="")},files:b};j([]),i.mutate({id:r?null:o.id,template:F},{onSuccess:()=>{a.success(s("admin.editor.saved")),n(xe)},onError:B=>{const $=B;j($.messages??[$.message??s("errors.generic")]),a.error(s("admin.editor.save_failed"))}})};return e.jsxs("div",{className:"ec-page",children:[e.jsxs("div",{className:"ec-between",children:[e.jsxs("div",{className:"ec-row",children:[e.jsxs(R,{variant:"ghost",onClick:()=>n(xe),children:[e.jsx(dt,{size:15})," ",s("common.back")]}),e.jsx("h1",{className:"ec-title",children:r?s("admin.editor.title_new"):o.id})]}),e.jsxs(R,{loading:i.isPending,onClick:g,children:[e.jsx(De,{size:15})," ",s("common.save")]})]}),x.length>0&&e.jsx(J,{children:e.jsx("ul",{className:"ec-error-list",children:x.map(b=>e.jsx("li",{children:b},b))})}),e.jsx(Tt,{active:c,onChange:b=>m(b),tabs:[{id:"edit",label:s("admin.editor.tab_edit")},{id:"preview",label:s("admin.editor.tab_preview")}]}),c==="edit"?e.jsxs("div",{className:"ec-stack",children:[e.jsx(J,{children:e.jsxs("div",{className:"ec-stack",children:[e.jsxs("div",{className:"ec-field-group",children:[e.jsx("label",{children:s("admin.editor.id")}),e.jsx(O,{value:o.id,disabled:!r,placeholder:"minecraft-vanilla",onChange:b=>k({id:b.target.value})})]}),e.jsxs("div",{className:"ec-cols-2",children:[e.jsxs("div",{className:"ec-field-group",children:[e.jsx("label",{children:s("admin.editor.name_en")}),e.jsx(O,{value:o.nameEn,onChange:b=>k({nameEn:b.target.value})})]}),e.jsxs("div",{className:"ec-field-group",children:[e.jsx("label",{children:s("admin.editor.name_fr")}),e.jsx(O,{value:o.nameFr,onChange:b=>k({nameFr:b.target.value})})]})]}),e.jsxs("div",{className:"ec-cols-2",children:[e.jsxs("div",{className:"ec-field-group",children:[e.jsx("label",{children:s("admin.editor.desc_en")}),e.jsx(O,{value:o.descEn,onChange:b=>k({descEn:b.target.value})})]}),e.jsxs("div",{className:"ec-field-group",children:[e.jsx("label",{children:s("admin.editor.desc_fr")}),e.jsx(O,{value:o.descFr,onChange:b=>k({descFr:b.target.value})})]})]}),e.jsxs("div",{className:"ec-field-group",children:[e.jsx("label",{children:s("admin.editor.author")}),e.jsx(O,{value:o.author,onChange:b=>k({author:b.target.value})})]})]})}),e.jsx(J,{children:e.jsx(Lt,{value:o.targetEggs,onChange:b=>k({targetEggs:b}),eggs:l.data??[],loading:l.isLoading})}),e.jsx(J,{children:e.jsxs("div",{className:"ec-stack",children:[e.jsxs("div",{className:"ec-between",children:[e.jsx("span",{className:"ec-field-label",children:s("admin.editor.boost_enabled")}),e.jsx(be,{checked:o.boostEnabled,onChange:b=>k({boostEnabled:b}),label:s("admin.editor.boost_enabled")})]}),o.boostEnabled&&e.jsxs("div",{className:"ec-field-group",children:[e.jsx("label",{children:s("admin.editor.boost_blacklist")}),e.jsx(O,{value:o.blacklist,placeholder:"server-port, rcon.port",onChange:b=>k({blacklist:b.target.value})}),e.jsx("span",{className:"ec-field-desc ec-muted",children:s("admin.editor.boost_blacklist_hint")})]})]})}),e.jsx(J,{children:e.jsxs("div",{className:"ec-field-group",children:[e.jsx("label",{children:s("admin.editor.files_json")}),e.jsx(Ce,{className:"ec-mono",value:o.filesJson,spellCheck:!1,onChange:b=>k({filesJson:b.target.value})})]})})]}):e.jsx(Jt,{files:We(o.filesJson)})]})}const Vt=`[
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
]`;function Ee(t){return typeof t=="object"&&t!==null&&!Array.isArray(t)}function ne(t,r=""){return typeof t=="string"?t:r}function Ge(){return{id:"",version:"1.0.0",nameEn:"",nameFr:"",descEn:"",descFr:"",author:"",targetEggs:[],boostEnabled:!1,blacklist:"",filesJson:Vt}}function Ht(t,r){if(r===null)return{...Ge(),id:t};const s=Ee(r.name)?r.name:{},n=Ee(r.description)?r.description:{},a=Ee(r.boost)?r.boost:{},l=Array.isArray(a.parameter_blacklist)?a.parameter_blacklist.map(String):[],i=Array.isArray(r.target_eggs)?r.target_eggs.filter(o=>typeof o=="number"):[];return{id:t,version:ne(r.version,"1.0.0"),nameEn:ne(s.en),nameFr:ne(s.fr),descEn:ne(n.en),descFr:ne(n.fr),author:ne(r.author),targetEggs:i,boostEnabled:a.enabled===!0,blacklist:l.join(", "),filesJson:JSON.stringify(r.files??[],null,2)}}function Xe(){var a;const{t}=z(),{templateId:r}=X.useParams(),s=r===void 0,n=Mt(s?null:r??null);return s?e.jsx(Ue,{initial:Ge(),isNew:!0}):n.isError&&((a=n.error)==null?void 0:a.status)===403?e.jsx("div",{className:"ec-page",children:e.jsx(J,{children:e.jsx(te,{children:t("admin.unauthorized")})})}):n.isLoading||n.data===void 0?e.jsx("div",{className:"ec-page",children:e.jsxs("div",{className:"ec-row ec-muted",children:[e.jsx(se,{})," ",t("common.loading")]})}):e.jsx(Ue,{initial:Ht(n.data.id,n.data.definition),isNew:!1},n.data.id)}function Te({open:t,onClose:r,title:s,children:n,footer:a,size:l="md",closeLabel:i}){return h.useEffect(()=>{if(!t)return;const o=u=>{u.key==="Escape"&&r()};return window.addEventListener("keydown",o),()=>window.removeEventListener("keydown",o)},[t,r]),t?e.jsx("div",{className:"ec-scrim",onMouseDown:o=>{o.target===o.currentTarget&&r()},children:e.jsxs("div",{className:A("ec-dialog",l==="lg"&&"ec-dialog-lg"),role:"dialog","aria-modal":"true",children:[e.jsxs("div",{className:"ec-dialog-head",children:[e.jsx("p",{className:"ec-dialog-title ec-grow",children:s}),e.jsx(ve,{label:i,onClick:r,children:e.jsx(Ie,{size:16})})]}),n,a!==void 0&&e.jsx("div",{className:"ec-dialog-foot",children:a})]})}):null}function Wt(){var k;const{t,lang:r}=z(),s=X.useNavigate(),n=ue(),a=zt(),l=Ft(),i=Ot(),[o,u]=h.useState(!1),[c,m]=h.useState("");if(a.isError&&((k=a.error)==null?void 0:k.status)===403)return e.jsx("div",{className:"ec-page",children:e.jsx(J,{children:e.jsx(te,{children:t("admin.unauthorized")})})});const x=g=>{window.confirm(t("admin.list.confirm_delete",{id:g}))&&l.mutate(g,{onSuccess:()=>n.success(t("admin.list.deleted")),onError:()=>n.error(t("errors.generic"))})},j=()=>{i.mutate(c,{onSuccess:()=>{n.success(t("admin.list.imported")),u(!1),m("")},onError:g=>n.error(g.message??t("admin.list.import_failed"))})};return e.jsxs("div",{className:"ec-page",children:[e.jsxs("div",{className:"ec-between",children:[e.jsxs("div",{children:[e.jsx("h1",{className:"ec-title",children:t("admin.list.title")}),e.jsx("p",{className:"ec-subtitle",children:t("admin.list.subtitle")})]}),e.jsxs("div",{className:"ec-row",children:[e.jsxs(R,{variant:"secondary",onClick:()=>u(!0),children:[e.jsx(St,{size:15})," ",t("admin.list.import")]}),e.jsxs(R,{onClick:()=>s(`${xe}/new`),children:[e.jsx(xt,{size:15})," ",t("admin.list.new")]})]})]}),a.isLoading?e.jsxs("div",{className:"ec-row ec-muted",children:[e.jsx(se,{})," ",t("common.loading")]}):a.data&&a.data.length>0?e.jsx("div",{className:"ec-grid",children:a.data.map(g=>e.jsxs(J,{hover:!0,className:"ec-template-card",children:[e.jsxs("div",{className:"ec-between",children:[e.jsx("strong",{className:"ec-truncate",children:Y(g.name,r,g.template_id)}),g.is_valid?e.jsxs(I,{variant:"success",children:["v",g.version]}):e.jsx(I,{variant:"warning",children:t("admin.list.invalid")})]}),e.jsx("span",{className:"ec-subtitle ec-truncate",children:g.template_id}),!g.is_valid&&g.last_error!==null&&e.jsx("span",{className:"ec-field-desc ec-truncate",children:g.last_error}),e.jsxs("div",{className:"ec-template-card-foot",children:[e.jsx(I,{variant:"muted",children:t("admin.list.files",{count:g.file_count})}),e.jsx(I,{variant:"muted",children:t("admin.list.eggs",{count:g.target_eggs.length})}),g.boost_enabled&&e.jsx(I,{variant:"accent",children:t("admin.list.boost")})]}),e.jsxs("div",{className:"ec-row",children:[e.jsxs(R,{size:"sm",variant:"secondary",onClick:()=>s(`${xe}/${g.template_id}`),children:[e.jsx(yt,{size:13})," ",t("common.edit")]}),e.jsxs("a",{className:"ec-btn ec-btn-ghost ec-btn-sm",href:`${M}/admin/templates/${g.template_id}/export`,children:[e.jsx(gt,{size:13})," ",t("common.export")]}),e.jsx(ve,{label:t("common.delete"),onClick:()=>x(g.template_id),children:e.jsx(Nt,{size:14})})]})]},g.template_id))}):e.jsx(J,{children:e.jsx(te,{children:t("admin.list.empty")})}),e.jsx(Te,{open:o,onClose:()=>u(!1),closeLabel:t("common.close"),title:t("admin.list.import_title"),footer:e.jsxs(e.Fragment,{children:[e.jsx(R,{variant:"ghost",onClick:()=>u(!1),children:t("common.cancel")}),e.jsx(R,{loading:i.isPending,onClick:j,children:t("admin.list.import")})]}),children:e.jsx("div",{className:"ec-dialog-body",children:e.jsx(Ce,{className:"ec-mono",value:c,placeholder:'{ "id": "minecraft-vanilla", ... }',onChange:g=>m(g.target.value)})})})]})}function Ut(){return e.jsx(Ke,{children:e.jsx("div",{className:"ec-root",children:e.jsxs(X.Routes,{children:[e.jsx(X.Route,{path:"",element:e.jsx(X.Navigate,{to:"manage",replace:!0})}),e.jsx(X.Route,{path:"manage",element:e.jsx(Wt,{})}),e.jsx(X.Route,{path:"manage/new",element:e.jsx(Xe,{})}),e.jsx(X.Route,{path:"manage/:templateId",element:e.jsx(Xe,{})})]})})})}const Re="";function je(t,r,s){return`${t}${Re}${r??""}${Re}${s}`}function ae(t,r){return je(t,r.section,r.key)}function Gt(t,r){return`${t}${Re}${r}`}function Xt(t,r){const s=t.config;switch(t.display_type){case"number":case"slider":{if(r.trim()===""||!Number.isFinite(Number(r)))return"number";const n=Number(r);return s.min!==void 0&&n<s.min?"min":s.max!==void 0&&n>s.max?"max":!s.float&&r.includes(".")?"integer":null}case"select":{const n=(s.options??[]).map(a=>a.value);return n.length===0||n.includes(r)?null:"option"}case"multiselect":{const n=s.separator&&s.separator!==""?s.separator:",",a=(s.options??[]).map(l=>l.value);if(a.length===0)return null;for(const l of r.split(n).map(i=>i.trim()).filter(i=>i!==""))if(!a.includes(l))return"option";return null}case"boolean":{const n=s.true_value??"true",a=s.false_value??"false";return r===n||r===a?null:"boolean"}case"text":{if(s.max_length!==void 0&&r.length>s.max_length)return"length";if(s.regex!==void 0&&s.regex!=="")try{if(!new RegExp(s.regex).test(r))return"format"}catch{return null}return null}case"textarea":return s.max_length!==void 0&&r.length>s.max_length?"length":null;case"color":return/^#?[0-9a-fA-F]{6}$/.test(r)?null:"color";default:return null}}function oe(t){if(!t)return"";const r=new Date(t);return Number.isNaN(r.getTime())?t:r.toLocaleString(void 0,{dateStyle:"short",timeStyle:"short"})}function Qe(t){const r=s=>String(s).padStart(2,"0");return`${t.getFullYear()}-${r(t.getMonth()+1)}-${r(t.getDate())}T${r(t.getHours())}:${r(t.getMinutes())}`}function Ze(t){return T.useQuery({queryKey:["ec-boosts",t],queryFn:()=>P(`${M}/servers/${t}/boosts`).then(r=>r.data)})}function Qt(t,r){return T.useQuery({queryKey:["ec-boost-history",t],enabled:r,queryFn:()=>P(`${M}/servers/${t}/boosts/history`).then(s=>s.data)})}function Zt(t){const r=T.useQueryClient();return T.useMutation({mutationFn:s=>P(`${M}/servers/${t}/boosts`,{method:"POST",body:JSON.stringify(s)}),onSuccess:()=>{r.invalidateQueries({queryKey:["ec-boosts",t]}),r.invalidateQueries({queryKey:["ec-config",t]})}})}function qt(t){const r=T.useQueryClient();return T.useMutation({mutationFn:s=>P(`${M}/servers/${t}/boosts/${s}`,{method:"DELETE"}),onSuccess:()=>{r.invalidateQueries({queryKey:["ec-boosts",t]}),r.invalidateQueries({queryKey:["ec-config",t]})}})}function er({open:t,onClose:r,serverId:s,params:n}){const{t:a}=z(),l=ue(),i=Zt(s),o=new Date,[u,c]=h.useState(new Set),[m,x]=h.useState({}),[j,k]=h.useState("2"),[g,b]=h.useState(Qe(o)),[F,B]=h.useState(Qe(new Date(o.getTime()+864e5))),$=N=>je(N.file_id,N.section,N.key),q=N=>{c(L=>{const C=new Set(L);return C.has(N)?C.delete(N):C.add(N),C})},ee=()=>{var L;const N=n.filter(C=>u.has($(C)));if(N.length===0||Number(j)<=0){l.error(a("boost.invalid"));return}i.mutate({template_id:((L=N[0])==null?void 0:L.template_id)??"",multiplier:Number(j),start_at:new Date(g).toISOString(),end_at:new Date(F).toISOString(),parameters:N.map(C=>{const D=m[$(C)];return{file_id:C.file_id,section:C.section,key:C.key,max_cap:D!==void 0&&D!==""?Number(D):null}})},{onSuccess:()=>{l.success(a("boost.created")),r()},onError:C=>{const D=C;l.error(D.code==="boost_overlap"?a("boost.overlap"):a("boost.create_failed"))}})};return e.jsx(Te,{open:t,onClose:r,closeLabel:a("common.close"),title:a("boost.new_title"),footer:e.jsxs(e.Fragment,{children:[e.jsx(R,{variant:"ghost",onClick:r,children:a("common.cancel")}),e.jsx(R,{loading:i.isPending,onClick:ee,children:a("boost.schedule")})]}),children:e.jsxs("div",{className:"ec-dialog-body",children:[e.jsx("div",{className:"ec-cols-2",children:e.jsxs("div",{className:"ec-field-group",children:[e.jsx("label",{children:a("boost.multiplier")}),e.jsx(O,{type:"number",min:0,step:"0.1",value:j,onChange:N=>k(N.target.value)})]})}),e.jsxs("div",{className:"ec-cols-2",children:[e.jsxs("div",{className:"ec-field-group",children:[e.jsx("label",{children:a("boost.start_at")}),e.jsx(O,{type:"datetime-local",value:g,onChange:N=>b(N.target.value)})]}),e.jsxs("div",{className:"ec-field-group",children:[e.jsx("label",{children:a("boost.end_at")}),e.jsx(O,{type:"datetime-local",value:F,onChange:N=>B(N.target.value)})]})]}),e.jsxs("div",{className:"ec-field-group",children:[e.jsx("label",{children:a("boost.parameters")}),e.jsxs("div",{className:"ec-egg-list",children:[n.map(N=>{const L=$(N),C=u.has(L);return e.jsxs("div",{className:"ec-server-row ec-check-row",style:{cursor:"default"},children:[e.jsxs("label",{className:"ec-row ec-grow",style:{cursor:"pointer"},children:[e.jsx("input",{type:"checkbox",checked:C,onChange:()=>q(L)}),e.jsx("span",{className:"ec-truncate",children:N.label})]}),C&&e.jsx(O,{className:"ec-input-narrow",type:"number",placeholder:a("boost.max_cap"),value:m[L]??"",onChange:D=>x(V=>({...V,[L]:D.target.value}))})]},L)}),n.length===0&&e.jsx("div",{className:"ec-empty",children:a("boost.no_boostable")})]})]}),e.jsxs("div",{className:"ec-row ec-secondary",children:[e.jsx(_e,{size:15})," ",e.jsx("span",{className:"ec-field-desc",children:a("boost.restart_warning")})]})]})})}function tr({serverId:t,boosts:r,onNew:s}){const{t:n}=z(),a=qt(t),[l,i]=h.useState(!1),o=Qt(t,l),u=c=>{window.confirm(n("boost.confirm_cancel"))&&a.mutate(c)};return e.jsx(J,{children:e.jsxs("div",{className:"ec-stack",children:[e.jsxs("div",{className:"ec-between",children:[e.jsx("h3",{className:"ec-title",children:n("boost.panel_title")}),e.jsxs("div",{className:"ec-row",children:[e.jsxs(R,{variant:"ghost",size:"sm",onClick:()=>i(c=>!c),children:[e.jsx(vt,{size:14})," ",n("boost.history")]}),e.jsxs(R,{size:"sm",onClick:s,children:[e.jsx(jt,{size:14})," ",n("boost.new")]})]})]}),r.length===0?e.jsx(te,{children:n("boost.none")}):e.jsx("div",{className:"ec-list",children:r.map(c=>e.jsxs("div",{className:"ec-server-row",style:{cursor:"default"},children:[e.jsxs(I,{variant:c.status==="active"?"accent":"info",children:["x",c.multiplier]}),e.jsxs("span",{className:"ec-grow",children:[e.jsx("span",{className:"ec-truncate",children:n("boost.param_count",{count:c.parameters.length})}),e.jsxs("span",{className:"ec-field-desc ec-muted",children:[oe(c.start_at)," ","→"," ",oe(c.end_at)]})]}),e.jsx(I,{variant:c.status==="active"?"success":"muted",children:n(`boost.status.${c.status}`)}),e.jsx(ve,{label:n("common.cancel"),onClick:()=>u(c.id),children:e.jsx(Ie,{size:14})})]},c.id))}),l&&e.jsxs("div",{className:"ec-list",children:[e.jsx("p",{className:"ec-section-label",children:n("boost.history")}),(o.data??[]).length===0?e.jsx(te,{children:n("boost.no_history")}):(o.data??[]).map(c=>e.jsxs("div",{className:"ec-server-row",style:{cursor:"default"},children:[e.jsxs(I,{variant:"muted",children:["x",c.multiplier]}),e.jsxs("span",{className:"ec-grow ec-truncate",children:[oe(c.start_at)," ","→"," ",oe(c.end_at)]}),e.jsx(I,{variant:"muted",children:n(`boost.final.${c.final_status}`)})]},c.id))]})]})})}const ze=(t,r)=>t[r]??!0;function rr({templates:t,selected:r,setSelected:s}){const{t:n,lang:a}=z(),l=t.flatMap(c=>c.files),i=c=>{s(m=>({...m,[c]:!ze(m,c)}))},o=c=>c.parameters.every(m=>ze(r,ae(c.id,m))),u=c=>{const m=!o(c);s(x=>{const j={...x};for(const k of c.parameters)j[ae(c.id,k)]=m;return j})};return l.length===0?e.jsx("div",{className:"ec-empty",children:n("copy.no_params")}):e.jsx("div",{className:"ec-stack",children:l.map(c=>e.jsxs("div",{className:"ec-section-group",children:[e.jsxs("label",{className:"ec-section-head ec-check-row",children:[e.jsx("input",{type:"checkbox",checked:o(c),onChange:()=>u(c)}),e.jsx("span",{children:Y(c.label,a,c.path)}),e.jsx("span",{className:"ec-section-count",children:c.parameters.length})]}),e.jsx("div",{className:"ec-section-body",children:c.parameters.map(m=>{const x=ae(c.id,m);return e.jsxs("label",{className:"ec-field ec-check-row",children:[e.jsx("input",{type:"checkbox",checked:ze(r,x),onChange:()=>i(x)}),e.jsx("span",{className:"ec-grow ec-truncate",children:Y(m.label,a,m.key)}),e.jsx("span",{className:"ec-field-desc ec-muted",children:m.section?`${m.section} · ${m.key}`:m.key})]},x)})})]},c.id))})}function sr({targetNames:t,paramCount:r,started:s,rows:n,expected:a,done:l}){const{t:i}=z();if(!s)return e.jsxs("div",{className:"ec-stack",children:[e.jsx("p",{children:i("copy.preview_summary",{params:r,servers:t.length})}),e.jsx("div",{className:"ec-list",children:t.map(c=>e.jsx("div",{className:"ec-server-row",children:e.jsx("span",{className:"ec-grow ec-truncate",children:c})},c))})]});const o=n.filter(c=>c.status==="success").length,u=n.length-o;return e.jsx("div",{className:"ec-stack",children:l?u>0?e.jsx(I,{variant:"warning",children:i("copy.recap_partial",{ok:o,fail:u})}):e.jsx(I,{variant:"success",children:i("copy.recap_success",{ok:o})}):e.jsxs("div",{className:"ec-row",children:[e.jsx(se,{})," ",e.jsxs("span",{children:[i("copy.in_progress")," (",n.length,"/",a,")"]})]})})}function nr({targets:t,selected:r,onToggle:s,loading:n}){const{t:a}=z(),[l,i]=h.useState(""),o=t.filter(u=>u.name.toLowerCase().includes(l.trim().toLowerCase()));return n?e.jsxs("div",{className:"ec-row ec-muted",children:[e.jsx(se,{})," ",a("common.loading")]}):t.length===0?e.jsx("div",{className:"ec-empty",children:a("copy.no_targets")}):e.jsxs("div",{className:"ec-stack",children:[e.jsxs("div",{className:"ec-search",children:[e.jsx("span",{className:"ec-search-icon",children:e.jsx(we,{size:14})}),e.jsx(O,{value:l,placeholder:a("copy.search_servers"),onChange:u=>i(u.target.value)})]}),e.jsx("div",{className:"ec-egg-list",children:o.map(u=>{const c=r.has(u.id);return e.jsxs("button",{type:"button",disabled:u.running,title:u.running?a("copy.running_tip"):void 0,className:A("ec-server-row",c&&"ec-server-row-on",u.running&&"ec-server-row-disabled"),onClick:()=>!u.running&&s(u.id),children:[u.egg.banner_image?e.jsx("img",{className:"ec-server-thumb",src:u.egg.banner_image,alt:""}):e.jsx("span",{className:"ec-server-thumb"}),e.jsxs("span",{className:"ec-grow",children:[e.jsx("span",{className:"ec-truncate",children:u.name}),e.jsxs("span",{className:"ec-field-desc ec-muted",children:[u.egg.name??"?"," · ",u.identifier]})]}),u.running?e.jsx(I,{variant:"warning",children:a("copy.running")}):c&&e.jsx(ge,{size:16})]},u.id)})})]})}function ar(t,r){return T.useQuery({queryKey:["ec-copy-targets",t],enabled:r,staleTime:3e4,queryFn:()=>P(`${M}/servers/${t}/copy/targets`).then(s=>s.data)})}function or(t){return T.useMutation({mutationFn:r=>P(`${M}/servers/${t}/copy`,{method:"POST",body:JSON.stringify(r)}).then(s=>s.data)})}function cr(t,r,s){return T.useQuery({queryKey:["ec-copy-log",t,r],enabled:r!==null,refetchInterval:n=>{var a;return(((a=n.state.data)==null?void 0:a.length)??0)>=s?!1:1500},queryFn:()=>P(`${M}/servers/${t}/copy/log?batch_id=${r??""}`).then(n=>n.data)})}function ir(t,r){const s=[];for(const n of t)for(const a of n.files){const l=a.parameters.filter(i=>r[ae(a.id,i)]??!0).map(i=>({key:i.key,section:i.section}));l.length>0&&s.push({id:a.id,params:l})}return s}function lr({open:t,onClose:r,serverId:s,templates:n}){var Z;const{t:a}=z(),l=ue(),[i,o]=h.useState(1),[u,c]=h.useState(new Set),[m,x]=h.useState({}),[j,k]=h.useState(null),[g,b]=h.useState(0),[F,B]=h.useState(!1),$=ar(s,t),q=Ze(s),ee=or(s),N=cr(s,j,g),L=h.useMemo(()=>ir(n,m),[n,m]),C=L.reduce((_,K)=>_+K.params.length,0),D=N.data??[],V=j!==null&&g>0&&D.length>=g,me=($.data??[]).filter(_=>u.has(_.id)).map(_=>_.name);h.useEffect(()=>{if(!V)return;const _=D.filter(W=>W.status==="success").length,K=D.length-_;K>0?l.warning(a("copy.recap_partial",{ok:_,fail:K})):l.success(a("copy.recap_success",{ok:_}))},[V]);const ce=()=>{o(1),c(new Set),x({}),k(null),b(0),B(!1)},H=()=>{ce(),r()},re=_=>{c(K=>{const W=new Set(K);return W.has(_)?W.delete(_):W.add(_),W})},pe=()=>{ee.mutate({targets:[...u],files:L,copy_boosts:F},{onSuccess:_=>{k(_.batch_id),b(_.targets),l.show(a("copy.in_progress"))},onError:()=>l.error(a("errors.generic"))})},G=j!==null?e.jsx(R,{onClick:H,children:a("common.close")}):i===1?e.jsxs(e.Fragment,{children:[e.jsx(R,{variant:"ghost",onClick:H,children:a("common.cancel")}),e.jsx(R,{disabled:u.size===0,onClick:()=>o(2),children:a("copy.next")})]}):i===2?e.jsxs(e.Fragment,{children:[e.jsx(R,{variant:"ghost",onClick:()=>o(1),children:a("common.back")}),e.jsx(R,{disabled:C===0,onClick:()=>o(3),children:a("copy.next")})]}):e.jsxs(e.Fragment,{children:[e.jsx(R,{variant:"ghost",onClick:()=>o(2),children:a("common.back")}),e.jsx(R,{loading:ee.isPending,onClick:pe,children:a("copy.confirm")})]});return e.jsx(Te,{open:t,onClose:H,closeLabel:a("common.close"),title:a("copy.title"),size:"lg",footer:G,children:e.jsxs("div",{className:"ec-dialog-body",children:[e.jsx("div",{className:"ec-steps",children:[1,2,3].map(_=>e.jsxs("span",{className:"ec-row",children:[e.jsx("span",{className:A("ec-step-dot",i>=_&&"ec-step-dot-active"),children:_}),_<3&&e.jsx("span",{className:"ec-step-bar"})]},_))}),i===1&&e.jsx(nr,{targets:$.data??[],selected:u,onToggle:re,loading:$.isLoading}),i===2&&e.jsx(rr,{templates:n,selected:m,setSelected:x}),i===3&&e.jsxs("div",{className:"ec-stack",children:[j===null&&(((Z=q.data)==null?void 0:Z.length)??0)>0&&e.jsxs("label",{className:"ec-row",style:{cursor:"pointer"},children:[e.jsx(be,{checked:F,onChange:B,label:a("copy.copy_boosts")}),e.jsx("span",{className:"ec-field-desc",children:a("copy.copy_boosts")})]}),e.jsx(sr,{targetNames:me,paramCount:C,started:j!==null,rows:D,expected:g,done:V})]})]})})}function dr({boost:t}){const{t:r}=z();return t.status==="active"?e.jsx(Se,{content:r("boost.active_tip",{mult:t.multiplier,end:oe(t.end_at),value:t.effective_value}),children:e.jsx("span",{children:e.jsxs(I,{variant:"accent",children:[e.jsx($e,{size:11})," x",t.multiplier," ","→"," ",t.effective_value]})})}):e.jsx(Se,{content:r("boost.planned_tip",{mult:t.multiplier,start:oe(t.start_at)}),children:e.jsx("span",{children:e.jsxs(I,{variant:"info",children:[e.jsx($e,{size:11})," x",t.multiplier]})})})}function ur({title:t,storageKey:r,count:s,children:n}){const[a,l]=h.useState(()=>{try{return localStorage.getItem(r)!=="0"}catch{return!0}}),i=()=>{l(o=>{const u=!o;try{localStorage.setItem(r,u?"1":"0")}catch{}return u})};return e.jsxs("div",{className:A("ec-section-group",!a&&"ec-section-collapsed"),children:[e.jsxs("button",{type:"button",className:"ec-section-head",onClick:i,"aria-expanded":a,children:[e.jsx("span",{className:"ec-section-chevron",children:e.jsx(ut,{size:16})}),e.jsx("span",{children:t}),s!==void 0&&e.jsx("span",{className:"ec-section-count",children:s})]}),a&&e.jsx("div",{className:"ec-section-body",children:n})]})}function mr(t){const r=new Map;for(const s of t){const n=r.get(s.section)??[];n.push(s),r.set(s.section,n)}return[...r.entries()]}function pr({file:t,controller:r,serverId:s}){const{t:n,lang:a}=z(),l=Y(t.label,a,t.path),i=r.search.trim().toLowerCase(),o=m=>{var x;return i===""?!0:Y(m.label,a,m.key).toLowerCase().includes(i)||m.key.toLowerCase().includes(i)||(((x=m.section)==null?void 0:x.toLowerCase().includes(i))??!1)},u=t.parameters.filter(o),c=m=>{const x=ae(t.id,m);return e.jsx(Je,{param:m,value:r.getValue(x),dirty:r.isDirty(x),saved:r.isSaved(x),invalid:r.isInvalid(x),disabled:r.disabled,onChange:j=>r.onChange(x,m,j),onReset:()=>r.onReset(x,m),boost:m.boost?e.jsx(dr,{boost:m.boost}):void 0},x)};return e.jsxs("div",{className:"ec-stack",children:[e.jsxs("div",{className:"ec-between",children:[e.jsx("h3",{className:"ec-title",children:l}),!t.exists&&e.jsx(I,{variant:"warning",children:n("file.missing_badge")})]}),t.exists?u.length===0?e.jsx(J,{children:e.jsx(te,{children:n("section.no_results")})}):t.sectioned?mr(u).map(([m,x])=>e.jsx(ur,{title:m??n("section.general"),storageKey:`ec:col:${s}:${t.id}:${m??""}`,count:x.length,children:x.map(c)},m??"_general")):e.jsx("div",{className:"ec-section-group",children:e.jsx("div",{className:"ec-section-body",children:u.map(c)})}):e.jsx(J,{children:e.jsx(te,{children:n("file.missing",{path:t.path})})})]})}function hr({saving:t,saved:r,onSave:s}){const{t:n}=z();return e.jsxs("div",{className:"ec-save-bar",children:[e.jsx("span",{className:"ec-save-bar-text",children:n(r?"save.saved":"save.unsaved")}),e.jsxs(R,{onClick:s,loading:t,disabled:r,children:[r?e.jsx(ge,{size:15}):e.jsx(De,{size:15}),n(r?"save.saved":"save.save")]})]})}function fr(t){return T.useQuery({queryKey:["ec-config",t],staleTime:1/0,queryFn:()=>P(`${M}/servers/${t}/config`).then(r=>r.data)})}function gr(t,r){return T.useQuery({queryKey:["ec-status",t],enabled:r,refetchInterval:s=>{var n;return((n=s.state.data)==null?void 0:n.state)==="offline"?!1:5e3},queryFn:()=>P(`${M}/servers/${t}/status`).then(s=>s.data)})}function xr(t){return T.useMutation({mutationFn:r=>P(`${M}/servers/${t}/config`,{method:"PUT",body:JSON.stringify({files:r})})})}function vr(t){return T.useMutation({mutationFn:r=>P(`${M}/servers/${t}/power`,{method:"POST",body:JSON.stringify({signal:r})})})}function br({serverId:t,templates:r,disabled:s}){const{t:n,lang:a}=z(),l=ue(),i=xr(t),{initial:o,index:u}=h.useMemo(()=>{const p={},f=new Map;for(const w of r)for(const S of w.files)for(const v of S.parameters){const U=ae(S.id,v);p[U]=v.value,f.set(U,{param:v,fileId:S.id})}return{initial:p,index:f}},[r]),[c,m]=h.useState(o),[x,j]=h.useState(o),[k,g]=h.useState({}),[b,F]=h.useState(new Set),[B,$]=h.useState(!1),[q,ee]=h.useState(()=>{try{return localStorage.getItem(`ec:search:${t}`)??""}catch{return""}}),N=p=>{ee(p);try{localStorage.setItem(`ec:search:${t}`,p)}catch{}},[L,C]=h.useState(!1),[D,V]=h.useState(!1),[me,ce]=h.useState(!1),H=Ze(t),re=r.some(p=>p.boost_enabled),pe=h.useMemo(()=>{const p=new Set((H.data??[]).flatMap(w=>w.parameters.map(S=>je(S.file_id,S.section,S.key)))),f=[];for(const w of r)if(w.boost_enabled)for(const S of w.files)for(const v of S.parameters)v.display_type!=="number"&&v.display_type!=="slider"||w.boost_blacklist.includes(v.key)||p.has(je(S.id,v.section,v.key))||f.push({template_id:w.id,file_id:S.id,section:v.section,key:v.key,label:Y(v.label,a,v.key),max:typeof v.config.max=="number"?v.config.max:void 0});return f},[r,H.data,a]),G=h.useMemo(()=>Object.keys(c).filter(p=>c[p]!==x[p]),[c,x]),Z=G.length>0,_=Object.values(k).some(Boolean),K=h.useCallback((p,f,w)=>{$(!1),m(v=>({...v,[p]:w}));const S=Xt(f,w);g(v=>({...v,[p]:S!==null})),S!==null&&l.warning(n("validation.invalid_value",{param:Y(f.label,a,f.key),type:n(`validation.type.${S}`)}))},[l,n,a]),W=h.useCallback((p,f)=>{f.config.default!==void 0&&K(p,f,String(f.config.default))},[K]),ie=h.useCallback(()=>{if(!Z||s||i.isPending)return;if(_){l.error(n("save.fix_invalid"));return}const p=new Map;for(const f of G){const w=u.get(f);if(w===void 0)continue;const S=p.get(w.fileId)??[];S.push({key:w.param.key,section:w.param.section,value:c[f]??""}),p.set(w.fileId,S)}i.mutate([...p.entries()].map(([f,w])=>({id:f,values:w})),{onSuccess:()=>{j({...c}),F(new Set(G)),$(!0),g({}),window.setTimeout(()=>{$(!1),F(new Set)},2e3),l.success(n("save.saved"))},onError:f=>{const w=f;if(w.status===422&&w.fields){const S={};for(const[v,U]of Object.entries(w.fields))for(const he of Object.keys(U))S[Gt(v,he)]=!0;g(S)}l.error(n("save.error"))}})},[Z,s,_,G,u,c,i,l,n]);h.useEffect(()=>{const p=f=>{(f.metaKey||f.ctrlKey)&&f.key.toLowerCase()==="s"&&(f.preventDefault(),ie())};return window.addEventListener("keydown",p),()=>window.removeEventListener("keydown",p)},[ie]);const d={getValue:p=>c[p]??"",isDirty:p=>c[p]!==x[p],isSaved:p=>b.has(p),isInvalid:p=>k[p]??!1,disabled:s,search:q,onChange:K,onReset:W},y=r.flatMap(p=>p.files.map(f=>({key:`${p.id}:${f.id}`,file:f})));return e.jsxs("div",{className:"ec-stack",children:[e.jsxs("div",{className:"ec-between",children:[e.jsxs("div",{className:"ec-row",children:[e.jsx("span",{className:"ec-icon-box",children:e.jsx(_t,{size:18})}),e.jsxs("div",{children:[e.jsx("h2",{className:"ec-title",children:n("section.title")}),e.jsx("p",{className:"ec-subtitle",children:n("section.subtitle")})]})]}),e.jsxs("div",{className:"ec-row",children:[re&&e.jsxs("label",{className:"ec-row",style:{cursor:"pointer"},children:[e.jsx("span",{className:"ec-field-desc ec-secondary",children:n("boost.mode")}),e.jsx(be,{checked:D,onChange:V,label:n("boost.mode")})]}),e.jsxs(R,{variant:"secondary",onClick:()=>C(!0),children:[e.jsx(ft,{size:15})," ",n("copy.button")]})]})]}),re&&D&&e.jsx(tr,{serverId:t,boosts:H.data??[],onNew:()=>ce(!0)}),e.jsxs("div",{className:"ec-search",children:[e.jsx("span",{className:"ec-search-icon",children:e.jsx(we,{size:14})}),e.jsx(O,{value:q,placeholder:n("section.search"),onChange:p=>N(p.target.value)})]}),y.map(({key:p,file:f})=>e.jsx(pr,{file:f,controller:d,serverId:t},p)),(Z||B)&&!s&&e.jsx(hr,{saving:i.isPending,saved:B,onSave:ie}),e.jsx(lr,{open:L,onClose:()=>C(!1),serverId:t,templates:r}),e.jsx(er,{open:me,onClose:()=>ce(!1),serverId:t,params:pe})]})}function yr({state:t,onStop:r,stopping:s}){const{t:n}=z();return e.jsx("div",{className:"ec-overlay",children:e.jsxs("div",{className:"ec-overlay-card",children:[e.jsx("span",{className:"ec-icon-box",children:e.jsx(_e,{size:20})}),e.jsx("p",{className:"ec-title",children:n("overlay.running_title")}),e.jsx("p",{className:"ec-subtitle",children:n("overlay.running_desc")}),e.jsxs(R,{onClick:r,loading:s||t==="stopping",children:[e.jsx(kt,{size:15})," ",n("overlay.stop_button")]})]})})}function jr({serverId:t}){var u,c;const{t:r}=z(),s=fr(t),n=(((u=s.data)==null?void 0:u.templates.length)??0)>0,a=gr(t,n),l=vr(t);if(s.isLoading)return e.jsxs("div",{className:"ec-card ec-row ec-muted",children:[e.jsx(se,{})," ",r("common.loading")]});if(s.isError||!s.data||!n)return null;const i=((c=a.data)==null?void 0:c.state)??"offline",o=a.isSuccess&&i!=="offline";return e.jsxs("div",{className:"ec-relative",children:[e.jsx(br,{serverId:t,templates:s.data.templates,disabled:o},t),o&&e.jsx(yr,{state:i,stopping:l.isPending,onStop:()=>l.mutate("stop")})]})}function kr({serverId:t}){return e.jsx(Ke,{children:e.jsx("div",{className:"ec-root",children:e.jsx(jr,{serverId:t})})})}const wr=`
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
`,_r=`
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
`,Nr=`
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
`,Sr=`
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
`,qe="easy-config-styles";function Cr(){if(typeof document>"u"||document.getElementById(qe))return;const t=document.createElement("style");t.id=qe,t.textContent=[_r,Nr,Sr,wr].join(`
`),document.head.appendChild(t)}Cr(),Ye.register("easy-configuration",Ut),Ye.registerServerHomeSection("easy-config",kr)})(window.__PEREGRINE_SHARED__.React,window.__PEREGRINE_SHARED__.ReactRouterDom,window.__PEREGRINE_SHARED__,window.__PEREGRINE_SHARED__.ReactQuery);
