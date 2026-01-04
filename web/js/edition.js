/*
 * @package ladecadanse
 * @copyright  Copyright (c) 2007 - 2025 Michel Gaudry <michel@ladecadanse.ch>
 * @license    AGPL License; see LICENSE file for details.
 */

'use strict';

tinymce.init({
    selector: 'textarea.tinymce',
    height: 500,
    menubar: false,
    plugins: ['autolink', 'lists', 'link', 'charmap', 'searchreplace', 'visualblocks', 'code', 'help', 'wordcount'],
    toolbar: 'bold italic link | h3 bullist numlist blockquote | undo redo | visualblocks removeformat code',
    content_css: [
    '//fonts.googleapis.com/css?family=Lato:300,300i,400,400i',
    '//www.tiny.cloud/css/codepen.min.css',
    ],
  relative_urls: false,
  remove_script_host: true
});