/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you require will output into a single css file (app.css in this case)
require('../css/app.scss'); // Node method
//import '../css/app.scss'; // EcmaScript 

import getNiceMessage from './get_nice_message'; // this is the official import method from EcmaScript Javascript language specification

//const getNiceMessage = require('./get_nice_message');  // require is the Node method to import

// Need jQuery? Install it with "yarn add jquery", then uncomment to require it.
// const $ = require('jquery');

console.log(getNiceMessage(6));
