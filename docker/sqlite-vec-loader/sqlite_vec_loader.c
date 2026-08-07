#include "php.h"
#include <sqlite3.h>
#include <sqlite3ext.h>

extern int sqlite3_vec_init(sqlite3 *db, char **pzErrMsg, const sqlite3_api_routines *pApi);

PHP_MINIT_FUNCTION(sqlite_vec_loader)
{
    return sqlite3_auto_extension((void (*)(void)) sqlite3_vec_init) == SQLITE_OK
        ? SUCCESS
        : FAILURE;
}

zend_module_entry sqlite_vec_loader_module_entry = {
    STANDARD_MODULE_HEADER,
    "sqlite_vec_loader",
    NULL,
    PHP_MINIT(sqlite_vec_loader),
    NULL,
    NULL,
    NULL,
    NULL,
    "0.1.0",
    STANDARD_MODULE_PROPERTIES,
};

ZEND_GET_MODULE(sqlite_vec_loader)
