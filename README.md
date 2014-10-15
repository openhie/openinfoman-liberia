openinfoman-liberia
===================

OpenInfoMan Liberia Customizations 

XQuery Libary for Liberia using CSD and supporting mHero use cases.

Prerequisites
=============

Assumes that you have installed BaseX and OpenInfoMan according to:
> https://github.com/openhie/openinfoman/wiki/Install-Instructions



Directions
==========
<pre>
cd ~/
git clone https://github.com/openhie/openinfoman-liberia
cd ~/openinfoman-libera/repo
basex -Vc "REPO INSTALL openinfoman-mhero.xqm"
cd ~/basex/resources/stored_updating_query_definitions
ln -sf ~/openinfoman-liberia/resources/stored_updating_query_definitions/* .
cd ~/basex/resources/shared_value_sets
ln -s ~/openinfoman-liberia/resources/shared_value_sets/* .
</pre>

Be sure to reload the stored functions: 
> https://github.com/openhie/openinfoman/wiki/Install-Instructions#Loading_Stored_Queries
