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
    withCredentials: true,
    requestInterceptor: async (request) => {
      const target = new URL(request.url, window.location.href);
      if (target.origin !== window.location.origin) throw new Error('A API deve usar a mesma origem do Swagger.');
      request.credentials = 'same-origin';
      if (!['GET', 'HEAD'].includes((request.method || 'GET').toUpperCase()) && !['/auth/login', '/auth/register'].includes(target.pathname)) {
        const response = await fetch('/auth/session', { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Entre em Autenticação antes de executar essa operação.');
        const session = await response.json();
        request.headers = { ...request.headers, 'X-CSRF-Token': session.csrf_token };
      }
      return request;
    },
    supportedSubmitMethods: ['get', 'post', 'patch'],
    presets: [SwaggerUIBundle.presets.apis],
    layout: 'BaseLayout',
  });
});
