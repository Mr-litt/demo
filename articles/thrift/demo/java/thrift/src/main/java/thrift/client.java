package thrift;

import Services.UserService.ApiErrorException;
import Services.UserService.UserListReq;
import Services.UserService.UserListResp;
import Services.UserService.UserService;
import org.apache.thrift.TException;
import org.apache.thrift.protocol.TBinaryProtocol;
import org.apache.thrift.protocol.TProtocol;
import org.apache.thrift.transport.TSocket;
import org.apache.thrift.transport.TTransport;
import org.apache.thrift.transport.TTransportException;

import java.util.ArrayList;
import java.util.List;

public class client {
    public static void main(String[] args) {
        System.out.println("客户端启动....");
        TTransport transport = null;
        try {
            transport = new TSocket("localhost", 9090, 30000);
            // 协议要和服务端一致
            TProtocol protocol = new TBinaryProtocol(transport);
            UserService.Client client = new UserService.Client(protocol);
            transport.open();
            List<Integer> uidList = new ArrayList<Integer>() {{
                 add(1);
                 add(2);
            }};
            UserListReq req = new UserListReq(uidList);
            UserListResp result = client.userList(req);
            System.out.println(result);
        } catch (TTransportException e) {
            e.printStackTrace();
        } catch (ApiErrorException e) {
            e.printStackTrace();
        } catch (TException e) {
            e.printStackTrace();
        } finally {
            if (null != transport) {
                transport.close();
            }
        }
    }
}
