# Bitácora de incidentes de despliegue — Sparta Gym

Registro de problemas reales ocurridos en producción (Render + Aiven), su causa
y solución, para que cualquier agente que retome el proyecto tenga contexto sin
repetir el diagnóstico desde cero. Ver también [DEPLOY_RENDER.md](DEPLOY_RENDER.md)
para la arquitectura completa del despliegue.

**Regla de esta bitácora:** nunca escribir contraseñas, tokens ni API keys reales
aquí (ni en ningún archivo del repo). Solo el diagnóstico y los pasos.

---

## 2026-08-13 — Deploy falla con "Name does not resolve" (Aiven DNS)

### Síntoma
Deploy en Render sale `Exited with status 1` durante `php artisan migrate --force`
(paso del [entrypoint.sh](entrypoint.sh)), con error:

```
SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo for
sparta-gym-db-sparta-gym.i.aivencloud.com failed: Name does not resolve
```

### Contexto en el que apareció
Justo después de subir un commit que solo tocaba UI/rutas de autenticación
(rename "Acceder" → "Login", ver `git log` commit `8ebb477`). Ese commit **no
tenía relación con la causa** — se descartó revisando `git show --stat` (solo
vistas, rutas y controladores, cero archivos de config/DB tocados).

### Causa raíz
El servicio de MySQL en **Aiven (plan free)** estaba apagado/pausado (o tenía
un *power off schedule* activo). Mientras el servicio está apagado, su hostname
deja de resolver en DNS por completo — no es un fallo intermitente de red, es
que el servicio no existe activo en ese momento. Se confirmó con `nslookup`
desde fuera de Render: `Non-existent domain`.

### Diagnóstico rápido para la próxima vez
1. `nslookup <host-aiven>` (o `ping`) — si da "Non-existent domain" / no
   resuelve, el servicio Aiven está apagado. Si resuelve, el problema es otro
   (credenciales, firewall, etc.).
2. Revisar que el commit que disparó el deploy realmente toque algo de DB/config
   antes de asumir que el código es la causa (`git show --stat <hash>`).

### Solución aplicada
1. Aiven Console → servicio `sparta-gym-db-sparta-gym` → **Power on**.
2. Revisar **Service settings → Power off schedule** y desactivarlo si se quiere
   el servicio corriendo 24/7 (plan free lo apaga por inactividad si no).
3. Como la contraseña de la BD se pegó en texto plano en un chat (fuera del
   repo, en la conversación con el asistente), se **rotó por seguridad**:
   Aiven → Users → `avnadmin` → Reset password → actualizar `DB_PASSWORD` en
   Render → Environment.
4. Render → Manual Deploy → Deploy latest commit.

### Pendiente / mejora sugerida
Hacer el [entrypoint.sh](entrypoint.sh) resiliente a un cold-start de Aiven:
agregar un retry/wait (p. ej. loop con `nc`/`mysqladmin ping` y backoff, unos
30-60s de margen) antes de `php artisan migrate --force`, para que un despertar
lento de Aiven no tumbe el deploy completo. **Estado: no implementado aún.**

---

## Plantilla para nuevos incidentes

```markdown
## AAAA-MM-DD — <resumen corto del síntoma>

### Síntoma
<qué se ve en los logs de Render, mensaje de error textual>

### Contexto en el que apareció
<qué se acababa de desplegar, commit relevante>

### Causa raíz
<qué falló realmente>

### Diagnóstico rápido para la próxima vez
<comandos o pasos para confirmar rápido la misma causa si se repite>

### Solución aplicada
<pasos que resolvieron el problema>

### Pendiente / mejora sugerida
<qué falta para que no vuelva a pasar>
```
