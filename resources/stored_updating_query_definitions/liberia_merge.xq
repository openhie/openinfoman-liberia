import module namespace csd_webconf =  "https://github.com/openhie/openinfoman/csd_webconf";

import module namespace csd_dm = "https://github.com/openhie/openinfoman/csd_dm";

import module namespace mhero = "https://github.com/openhie/openinfoman-mhero";

declare namespace csd  =  "urn:ihe:iti:csd:2013";

declare variable $careServicesRequest as item() external;


let $mhero_doc := /.
let $mhero := $careServicesRequest/@resource
let $mhero := $careServicesRequest/documents/mhero/@resource


for $doc  in $careServicesRequest/documents/document
let $name := $doc/@resource
let $src_doc :=
  if (not ($name  = '')) 
  then if (not ($name = $mhero)) then csd_dm:open_document($csd_webconf:db, $name) else ()
  else $doc
return mhero:merge_into($mhero_doc, $src_doc) 

