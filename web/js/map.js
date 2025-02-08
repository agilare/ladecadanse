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

    var myLatLng = {lat: parseFloat($('#lieu-map').data('lat')), lng: parseFloat($('#lieu-map').data('lng'))};

    lieuMap = new google.maps.Map(document.getElementById('lieu-map'), {
        center: myLatLng,
        zoom: 14
    });

    var marker = new google.maps.Marker({
        position: myLatLng,
        map: lieuMap
    });

    var infowindow = new google.maps.InfoWindow({
        content: $('#lieu-map-infowindow').html()
    });

    marker.addListener('click', function ()
    {
        infowindow.open(lieuMap, marker);
    });

}

