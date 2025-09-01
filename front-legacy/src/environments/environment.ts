// This file can be replaced during build by using the `fileReplacements` array.
// `ng build --prod` replaces `environment.ts` with `environment.prod.ts`.
// The list of file replacements can be found in `angular.json`.

export const environment = {
  production: false,
  // Your web app's Firebase configuration
  // For Firebase JS SDK v7.20.0 and later, measurementId is optional
  baseUrl: 'https://dev.api.boukii.com/api',
  firebaseConfig: {
    apiKey: "AIzaSyAVqgEm3-_sMPLqxySQpyHKEfLtQ1_7pHI",
    authDomain: "boukii-test.firebaseapp.com",
    projectId: "boukii-test",
    storageBucket: "boukii-test.appspot.com",
    messagingSenderId: "80492512236",
    appId: "1:80492512236:web:bba5002b4c9ec6c2e776c9"
  }
};

/*
 * For easier debugging in development mode, you can import the following file
 * to ignore zone related error stack frames such as `zone.run`, `zoneDelegate.invokeTask`.
 *
 * This import should be commented out in production mode because it will have a negative impact
 * on performance if an error is thrown.
 */
// import 'zone.js/plugins/zone-error';  // Included with Angular CLI.
