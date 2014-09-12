import module namespace  zip = "http://expath.org/ns/zip";
import module namespace functx = "http://www.functx.com";

declare namespace csd = "urn:ihe:iti:csd:2013"; 



(: docProps/core.xml namespaces :)
declare namespace cprop = "http://schemas.openxmlformats.org/package/2006/metadata/core-properties" ;
declare namespace dc = "http://purl.org/dc/elements/1.1/";
declare namespace dcterms = "http://purl.org/dc/terms/";
declare namespace dcmitype = "http://purl.org/dc/dcmitype/" ;

(:docProps/app.xml namesspaces :)
declare namespace prop = "http://schemas.openxmlformats.org/officeDocument/2006/extended-properties";
declare namespace vect = "http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes";

(:xl/worksheets/sheet*.xml namespaces :)
declare namespace sheet = "http://schemas.openxmlformats.org/spreadsheetml/2006/main";
declare namespace rel = "http://schemas.openxmlformats.org/officeDocument/2006/relationships";
declare namespace markup = "http://schemas.openxmlformats.org/markup-compatibility/2006";
declare namespace x14ac = "http://schemas.microsoft.com/office/spreadsheetml/2009/9/ac";



let $file := "Bong.xlsx"
let $base-urn  := concat("urn:x-excelfile:" , $file ,":gCHV")
let $gchv-type-code-scheme := "2.25.17301777793732765478144807142873029913013910280806"


let $normalize-name := function ($name) {
  replace(lower-case(string($name)),"[^a-z]","")
}

let $dhis_doc := doc("dhis2_training_liberia_as_CSD.xml") (:could also be something pointing at an SVS file or the DB :)

let $facs := 
  for $fac in $dhis_doc/csd:CSD/csd:facilityDirectory/csd:facility
  return <facility urn="{$fac/@urn}" name="{$normalize-name($fac/csd:primaryName/text())}"/>

let $orgs := 
  for $org in $dhis_doc/csd:CSD/csd:organizationDirectory/csd:organization
  return <organization urn="{$org/@urn}" name="{$normalize-name($org/csd:primaryName/text())}"/>


let $lookup-facility-id := function($name) {
  let $n-name := $normalize-name($name)
  let $fac := ($facs[@name = $n-name])[1]
  return
    if (exists($fac))
    then string($fac/@urn)
    else concat($base-urn, ":facility:",$n-name)
}


let $lookup-organization-id := function($name) {
  let $n-name := $normalize-name($name)
  let $org := ($orgs[@name = $n-name])[1]
  return
    if (exists($org))
    then string($org/@urn)
    else concat($base-urn, ":organization:" ,  ':' , $n-name)
}



let $sheet-list := zip:xml-entry($file,"docProps/app.xml")/prop:Properties/prop:TitlesOfParts/vect:vector/vect:lpstr
let $contact-sheet-pos := functx:index-of-node($sheet-list,$sheet-list[./text() = "Contacts"])
let $contact-sheet-file := concat("xl/worksheets/sheet", $contact-sheet-pos , '.xml')
let $modified := substring-before(string(zip:xml-entry($file,"docProps/core.xml")/cprop:coreProperties/dcterms:modified),'Z')
let $shared-strings := zip:xml-entry($file,"xl/sharedStrings.xml")


let $lookup-ref := function($ref) {
  let $lookup := $shared-strings/sheet:sst/sheet:si[position() = ($ref + 1)]/sheet:t
  return 
     if (exists($lookup)) 
     then  $lookup/text()
     else concat('#',$ref)
}

let $get-cell-value := function($cell) {
  let $text := $cell/sheet:v/text()
  return 
    if ($cell/@t = "s")
    then $lookup-ref($text)
    else $text
}


let $contacts :=
  for $contact in zip:xml-entry($file,$contact-sheet-file)/sheet:worksheet/sheet:sheetData/sheet:row[position() > 1]
  let $row := string($contact/@r)
  let $id := $get-cell-value($contact/sheet:c[@r = concat("A",$row)])
  let $cty := $get-cell-value($contact/sheet:c[@r = concat("B",$row)])
  let $dis := $get-cell-value($contact/sheet:c[@r = concat("C",$row)])
  let $fac := $get-cell-value($contact/sheet:c[@r = concat("D",$row)])
  let $name := $get-cell-value($contact/sheet:c[@r = concat("E",$row)])
  let $pos := $get-cell-value($contact/sheet:c[@r = concat("F",$row)])
  let $cell := $get-cell-value($contact/sheet:c[@r = concat("G",$row)])
  let $urn := concat($base-urn,":provider:",$id)
  let $ghcv-type := $normalize-name($pos)

  return 
    <csd:provider urn="{$urn}" >
      <csd:codedType code="{$ghcv-type}" codingScheme="{$gchv-type-code-scheme}" /> 
      <csd:demographic>
	<csd:name>
	  <csd:commonName>{$name}</csd:commonName>
	</csd:name>
	{
	  if (not (functx:all-whitespace($cell))) 
	  then 
	    <csd:contactPoint>
              <csd:codedType code="BP" codingScheme="urn:ihe:iti:csd:2013:contactPoint">{$cell}</csd:codedType>
	    </csd:contactPoint>
          else ()
	}
	{
	  let $orglist := 
	    (
	      if (not(functx:all-whitespace($cty))) then $cty else (),
	      if (not(functx:all-whitespace($dis))) then $dis else ()
	    )
	  return 
	    if (count($orglist) > 0)
	    then 
	      <csd:organizations>
		{
		  for $org in $orglist
		  return <csd:organization urn="{$lookup-organization-id($org)}"></csd:organization>
		}
	      </csd:organizations>
	    else ()
	}
	{
	  if (not (functx:all-whitespace($fac))) 
	  then <csd:facilities><csd:facility urn="{$lookup-facility-id($fac)}"/></csd:facilities>
	  else  ()
	}

	<csd:record created="{$modified}" updated="{$modified}" status="106-001" sourceDirectory="file://{$file}"/>
      </csd:demographic>
    </csd:provider>


let $csd := 
  <csd:CSD>
    <csd:organizationDirectory/>
    <csd:serviceDirectory/>
    <csd:facilityDirectory/>
    <csd:providerDirectory>{$contacts}</csd:providerDirectory>
  </csd:CSD>

let $svs-gchv := 
  <svs:ValueSet xmlns:svs="urn:ihe:iti:svs:2008" id="{$gchv-type-code-scheme}" version="20131201" displayName="gCHV Types">
    <svs:ConceptList xml:lang="en-US">

      {
	let $ghcv-types := 
	  for $contact in zip:xml-entry($file,$contact-sheet-file)/sheet:worksheet/sheet:sheetData/sheet:row[position() > 1]
	    let $row := string($contact/@r)
	    return $get-cell-value($contact/sheet:c[@r = concat("F",$row)])
         return
	   for $ghcv-type in $ghcv-types 
	   let $code := $normalize-name( $ghcv-type)
           return <svs:Concept code="{$code}" displayName="gCHV: {$ghcv-type}" codeSystem="{$gchv-type-code-scheme}"/>
       }
    </svs:ConceptList>
  </svs:ValueSet>


return 
  (
    file:write(concat($file,'.csd.xml'),$csd)
    ,file:write(concat($file,'.svs.xml'),$svs-gchv)
  )


