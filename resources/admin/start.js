// Entry point. The app lives in app.js, its own chunk, so no route chunk ever
// imports this file: start.js?ver= from the <script> tag stays its only URL.
import { bootApp } from './app';

bootApp();
