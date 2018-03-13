# -*- coding: UTF-8 -*-
import sys
sys.path.append('gen-py')

from Services.UserService import UserService
from Services.UserService.ttypes import UserListReq, UserListResp, UserInfo, ApiErrorException

from thrift import Thrift
from thrift.transport import TSocket
from thrift.transport import TTransport
from thrift.protocol import TBinaryProtocol

def main():

    try:
        transport = TSocket.TSocket('localhost', 9090)
        transport = TTransport.TBufferedTransport(transport)
        protocol = TBinaryProtocol.TBinaryProtocol(transport)
        client = UserService.Client(protocol)
        # Connect
        transport.open()
        # request
        req = UserListReq()
        req.uidList = [1, 2]
        result = client.userList(req)
        print result

        # Close
        transport.close()
    except Thrift.TException as e:
        print e
    except ApiErrorException as e:
        print e

if __name__ == '__main__':
    main()