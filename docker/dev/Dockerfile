ARG NC_VERSION=33
FROM nextcloud:${NC_VERSION}-apache

COPY --chmod=755 setup.sh /docker-entrypoint-hooks.d/before-starting/10-ticky-crm.sh
COPY --chmod=755 entrypoint.sh /ticky-dev-entrypoint.sh
ENTRYPOINT ["/ticky-dev-entrypoint.sh"]
CMD ["apache2-foreground"]
