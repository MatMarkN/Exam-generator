window.addEventListener('DOMContentLoaded', () => {
  window.ui = SwaggerUIBundle({
    url: '/openapi.json',
    dom_id: '#swagger-ui',
    deepLinking: true,
    docExpansion: 'list',
    defaultModelsExpandDepth: -1,
    displayRequestDuration: true,
    filter: true,
    validatorUrl: null,
    queryConfigEnabled: false,
    persistAuthorization: false,
    supportedSubmitMethods: ['get', 'post', 'patch'],
    presets: [SwaggerUIBundle.presets.apis],
    layout: 'BaseLayout',
  });
});
