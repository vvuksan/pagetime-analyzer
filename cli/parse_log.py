# -*- coding: utf-8 -*-
import os
import sys
import threading
import time
import optparse
import stat
import re
from time import time

## globals
# If you are dealing with gz/bz2 files you could use lesspipe
# to cat the file. If you don't please change this to e.g. /bin/cat
lesspipe_path = "/usr/bin/lesspipe"


def main():

  # Parse command line options
  cmdline = optparse.OptionParser()
  cmdline.add_option('--log_file', '-l', action='store', help='The path to the file to parse')
  cmdline.add_option('--server', '-s', action='store', default='localhost', help='Name of the memcache server where data is stored. Defaults to localhost if none supplied')
  cmdline.add_option('--instance_name', '-n', action='store', default='', help='Name of the instance/web server for which we are processing logs')
  options, arguments = cmdline.parse_args()
  log_file = options.log_file
  memcache_server = options.server

  # Import the class
  class_name = "PageTimeAnalyzer"
  module = __import__(class_name)
  # Initialize the parser
  parser = getattr(module, class_name)()

  ################################################################################
  # By default instance_name will be initialized as the hostname of the machine
  # parser is running. If 
  ################################################################################
  if options.instance_name != '':
    parser.set_instance_name(options.instance_name)

  ################################################################################
  # Initialize memcache
  ################################################################################
  parser.initialize_memcache(memcache_server)

  ################################################################################
  # Let's define command to pipe the output. We are using lesspipe
  # since it recognizes all sorts of different file types ie. plain, gz, bz2
  shell_tail = '%s %s' % (lesspipe_path, log_file)

  print "Processing " + log_file
  
  input = os.popen(shell_tail)

  line_count = 0
  
  start_time = time()

  # Parse the log file line by line
  for line in input:
  
    line_count += 1 
  
    try:
      parser.parse_line(line)

    except Exception, e:
      print ( "Parsing exception caught at %s" % ( e))
      
  end_time = time()
  run_time = end_time - start_time

  lines_per_sec = line_count / run_time
  print "---> Parsed lines=%s, GET/HTTP 200 lines=%s Time=%.2f seconds. Lines/second=%.0f" % (line_count, parser.lines_processed, run_time, lines_per_sec)
  start_time = time()
  print "---> aggregating data for memcache"
  parser.send_to_memcache()
  end_time = time()
  run_time = end_time - start_time
  print "---> memcache aggregation time=%.2f seconds" % (run_time)
  
  
if __name__ == '__main__':
    main()
    
    
