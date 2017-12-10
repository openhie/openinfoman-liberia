import module namespace csd_bl = "https://github.com/openhie/openinfoman/csd_bl";
declare default element  namespace   "urn:ihe:iti:csd:2013";
declare variable $careServicesRequest as item() external;

let $district_uuid := string($careServicesRequest/organization/@entityID)
let $data := /CSD/providerDirectory/provider
    [
      (
        starts-with(lower-case(string(./extension/position/@title)),"dso") or
        starts-with(lower-case(string(./extension/position/@title)),"district surveillance")
      )
      and ./facilities/facility/@entityID = /CSD/facilityDirectory/facility[./organizations/organization[@entityID = $district_uuid]]/@entityID
    ]
return <CSD xmlns:csd="urn:ihe:iti:csd:2013"  >
        <organizationDirectory/>
        <serviceDirectory/>
        <facilityDirectory/>
        <providerDirectory>
          {$data}
        </providerDirectory>
       </CSD>
