/*
 * @package ladecadanse
 * @copyright  Copyright (c) 2007 - 2025 Michel Gaudry <michel@ladecadanse.ch>
 * @license    AGPL License; see LICENSE file for details.
 */

'use strict';

let lieuMap;

function initLieuMap()
{
    if ($('#lieu-map').length === 0)
    {
        return;
    }

    // Resize map on display as it was initialized hidden
    $('.dropdown').click(function dropdownTarget(){
        if ($(this).data('target') == "plan"){
            setTimeout(() => lieuMap.invalidateSize(), 1);
        }
    });
    var myLatLng = {lat: parseFloat($('#lieu-map').data('lat')), lng: parseFloat($('#lieu-map').data('lng'))};

    lieuMap = L.map('lieu-map')
        .setView(myLatLng, 14);
    
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
		maxZoom: 19,
		attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
	}).addTo(lieuMap);

    L.marker(myLatLng)
        .addTo(lieuMap)
        .bindPopup($('#lieu-map-infowindow').html());
}

initLieuMap();