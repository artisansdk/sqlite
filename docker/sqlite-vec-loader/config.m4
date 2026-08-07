PHP_ARG_ENABLE([sqlite_vec_loader], [whether to enable sqlite-vec], [AS_HELP_STRING([--enable-sqlite-vec-loader], [Enable sqlite-vec])])

if test "$PHP_SQLITE_VEC_LOADER" != "no"; then
    PHP_NEW_EXTENSION([sqlite_vec_loader], [sqlite_vec_loader.c sqlite-vec.c], [$ext_shared])
    PHP_ADD_LIBRARY([sqlite3], [1], [SQLITE_VEC_LOADER_SHARED_LIBADD])
    PHP_SUBST([SQLITE_VEC_LOADER_SHARED_LIBADD])
fi
